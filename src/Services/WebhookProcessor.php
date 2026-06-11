<?php

declare(strict_types=1);

namespace Mostafax\PaymentEngine\Services;

use Illuminate\Http\Request;
use Mostafax\PaymentEngine\Contracts\WebhookHandlerInterface;
use Mostafax\PaymentEngine\Enums\WebhookStatus;
use Mostafax\PaymentEngine\Jobs\ProcessWebhookJob;
use Mostafax\PaymentEngine\Models\WebhookPayload;
use Mostafax\PaymentEngine\Repositories\WebhookRepository;

final class WebhookProcessor implements WebhookHandlerInterface
{
    public function __construct(
        private readonly WebhookRepository $webhookRepo,
        private readonly PaymentManager    $paymentManager,
    ) {}

    // -----------------------------------------------------------------------
    // Step 1 — Persist raw request before processing
    // -----------------------------------------------------------------------

    public function persist(Request $request, string $gateway): WebhookPayload
    {
        $key      = $this->idempotencyKey($request->all(), $gateway);
        $verified = $this->verifySignature($request, $gateway);

        if ($this->webhookRepo->isDuplicate($key)) {
            $webhook = $this->webhookRepo->persist($request, $gateway, $key . '_dup_' . time(), $verified);
            $webhook->update(['processing_status' => WebhookStatus::Duplicate]);
            return $webhook;
        }

        return $this->webhookRepo->persist($request, $gateway, $key, $verified);
    }

    // -----------------------------------------------------------------------
    // Step 2 — Signature verification
    // -----------------------------------------------------------------------

    public function verifySignature(Request $request, string $gateway): bool
    {
        // Each gateway may override this. Default: pass-through.
        // Production: validate HMAC against gateway's secret.
        return match ($gateway) {
            'stripe' => $this->verifyStripeSignature($request),
            default  => true,
        };
    }

    // -----------------------------------------------------------------------
    // Step 3 — Idempotency key derivation
    // -----------------------------------------------------------------------

    public function idempotencyKey(array $payload, string $gateway): string
    {
        $seed = match ($gateway) {
            'knet'       => ($payload['trackid'] ?? '') . ($payload['paymentid'] ?? ''),
            'myfatoorah' => ($payload['InvoiceId'] ?? '') . ($payload['PaymentId'] ?? ''),
            'tap'        => $payload['id'] ?? '',
            'stripe'     => $payload['id'] ?? '',
            'paytabs'    => $payload['tran_ref'] ?? '',
            'paypal'     => $payload['resource']['id'] ?? '',
            default      => json_encode($payload),
        };

        return $gateway . '_' . hash('sha256', $seed);
    }

    // -----------------------------------------------------------------------
    // Step 4 — Queue for reliable processing
    // -----------------------------------------------------------------------

    public function queueForProcessing(WebhookPayload $webhook): void
    {
        if ($webhook->isDuplicate() || $webhook->isDeadLetter()) {
            return;
        }

        $webhook->update(['processing_status' => WebhookStatus::Processing]);

        ProcessWebhookJob::dispatch($webhook->id)
            ->onQueue(config('payment-engine.queues.webhooks', 'payment-webhooks'));
    }

    // -----------------------------------------------------------------------
    // Step 5 — Actual processing (called from Job)
    // -----------------------------------------------------------------------

    public function process(WebhookPayload $webhook): void
    {
        try {
            $payload     = json_decode($webhook->raw_body, associative: true) ?? [];
            $transaction = $this->paymentManager->handleCallback($webhook->gateway, $payload);

            $webhook->update(['transaction_id' => $transaction->id]);
            $webhook->markProcessed();

            $this->webhookRepo->recordAttempt($webhook, true);
        } catch (\Throwable $e) {
            $attemptNumber = $webhook->attempt_count + 1;

            $this->webhookRepo->recordAttempt($webhook, false, $e);
            $webhook->markFailed($e->getMessage(), $attemptNumber);

            throw $e;
        }
    }

    // -----------------------------------------------------------------------
    // Private
    // -----------------------------------------------------------------------

    private function verifyStripeSignature(Request $request): bool
    {
        $secret    = config('payment-engine.gateways.stripe.webhook_secret');
        $header    = $request->header('Stripe-Signature', '');
        $payload   = $request->getContent();

        if (! $secret || ! $header) {
            return false;
        }

        preg_match('/t=(\d+)/', $header, $tMatch);
        preg_match('/v1=([^,]+)/', $header, $vMatch);

        $timestamp     = $tMatch[1] ?? '';
        $signature     = $vMatch[1] ?? '';
        $signedPayload = "{$timestamp}.{$payload}";
        $expected      = hash_hmac('sha256', $signedPayload, $secret);

        return hash_equals($expected, $signature);
    }
}

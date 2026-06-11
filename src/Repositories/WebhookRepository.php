<?php

declare(strict_types=1);

namespace Mostafax\PaymentEngine\Repositories;

use Illuminate\Http\Request;
use Mostafax\PaymentEngine\Enums\WebhookStatus;
use Mostafax\PaymentEngine\Models\WebhookAttempt;
use Mostafax\PaymentEngine\Models\WebhookPayload;

final class WebhookRepository
{
    public function persist(Request $request, string $gateway, string $idempotencyKey, bool $signatureVerified): WebhookPayload
    {
        return WebhookPayload::create([
            'gateway'            => $gateway,
            'raw_body'           => $request->getContent(),
            'headers'            => $request->headers->all(),
            'ip_address'         => $request->ip(),
            'http_method'        => $request->method(),
            'idempotency_key'    => $idempotencyKey,
            'signature_verified' => $signatureVerified,
            'processing_status'  => WebhookStatus::Received,
        ]);
    }

    public function isDuplicate(string $idempotencyKey): bool
    {
        return WebhookPayload::where('idempotency_key', $idempotencyKey)
            ->whereIn('processing_status', [
                WebhookStatus::Processed->value,
                WebhookStatus::Processing->value,
                WebhookStatus::Duplicate->value,
            ])
            ->exists();
    }

    public function recordAttempt(WebhookPayload $webhook, bool $success, ?\Throwable $exception = null): void
    {
        WebhookAttempt::create([
            'webhook_payload_id' => $webhook->id,
            'attempt_number'     => $webhook->attempt_count + 1,
            'success'            => $success,
            'exception_message'  => $exception?->getMessage(),
            'exception_trace'    => $exception?->getTraceAsString(),
        ]);
    }

    public function pendingRetry(): \Illuminate\Database\Eloquent\Collection
    {
        return WebhookPayload::pendingRetry()->get();
    }

    public function deadLetterQueue(): \Illuminate\Database\Eloquent\Collection
    {
        return WebhookPayload::where('processing_status', WebhookStatus::DeadLetter->value)->get();
    }
}

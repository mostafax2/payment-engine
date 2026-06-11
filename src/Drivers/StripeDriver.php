<?php

declare(strict_types=1);

namespace Mostafax\PaymentEngine\Drivers;

use Mostafax\PaymentEngine\DTOs\GatewayResponseDTO;
use Mostafax\PaymentEngine\DTOs\InitiatePaymentDTO;
use Mostafax\PaymentEngine\Enums\TransactionStatus;

final class StripeDriver extends AbstractPaymentDriver
{
    public function __construct()
    {
        parent::__construct('stripe');
    }

    public function getGatewayName(): string
    {
        return 'stripe';
    }

    protected function defaultHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->cfg('secret_key'),
            'Content-Type'  => 'application/x-www-form-urlencoded',
        ];
    }

    public function initiate(InitiatePaymentDTO $dto): array
    {
        $response = $this->post($this->cfg('base_url') . '/checkout/sessions', [
            'mode'               => 'payment',
            'client_reference_id'=> $dto->trackId,
            'success_url'        => $dto->successUrl,
            'cancel_url'         => $dto->errorUrl,
            'line_items'         => [[
                'price_data' => [
                    'currency'     => strtolower($dto->currency),
                    'unit_amount'  => (int) ($dto->amount * 100),
                    'product_data' => ['name' => $dto->description ?? 'Payment'],
                ],
                'quantity' => 1,
            ]],
        ]);

        return [
            'redirect_url' => $response['url']    ?? '',
            'session_id'   => $response['id']     ?? null,
            'track_id'     => $dto->trackId,
        ];
    }

    public function handleCallback(array $payload): GatewayResponseDTO
    {
        $sessionId = $payload['data']['object']['id'] ?? $payload['session_id'] ?? null;

        $session  = $this->get($this->cfg('base_url') . '/checkout/sessions/' . $sessionId);
        $captured = ($session['payment_status'] ?? '') === 'paid';

        return new GatewayResponseDTO(
            success:               $captured,
            status:                $captured ? TransactionStatus::Captured : TransactionStatus::Failed,
            gatewayTransactionId:  $session['payment_intent']       ?? null,
            trackId:               $session['client_reference_id']  ?? null,
            amount:                isset($session['amount_total']) ? $session['amount_total'] / 100 : null,
            currency:              strtoupper($session['currency'] ?? ''),
            gatewayStatus:         $session['payment_status'] ?? null,
            rawPayload:            $session,
        );
    }

    public function inquire(string $trackId, ?float $amount = null): GatewayResponseDTO
    {
        $sessions = $this->get($this->cfg('base_url') . '/checkout/sessions', [
            'client_reference_id' => $trackId,
            'limit'               => 1,
        ]);

        $session  = $sessions['data'][0] ?? [];
        $captured = ($session['payment_status'] ?? '') === 'paid';

        return new GatewayResponseDTO(
            success:       $captured,
            status:        $captured ? TransactionStatus::Captured : TransactionStatus::Unknown,
            gatewayStatus: $session['payment_status'] ?? null,
            rawPayload:    $session,
        );
    }

    public function fetchTransactions(string $from, string $to, int $page = 1, int $perPage = 500): array
    {
        $fromTs = strtotime($from);
        $toTs   = strtotime($to);

        $response = $this->get($this->cfg('base_url') . '/charges', [
            'created[gte]' => $fromTs,
            'created[lte]' => $toTs,
            'limit'        => $perPage,
        ]);

        return $response['data'] ?? [];
    }
}

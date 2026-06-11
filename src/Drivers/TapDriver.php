<?php

declare(strict_types=1);

namespace Mostafax\PaymentEngine\Drivers;

use Mostafax\PaymentEngine\DTOs\GatewayResponseDTO;
use Mostafax\PaymentEngine\DTOs\InitiatePaymentDTO;
use Mostafax\PaymentEngine\Enums\TransactionStatus;

final class TapDriver extends AbstractPaymentDriver
{
    public function __construct()
    {
        parent::__construct('tap');
    }

    public function getGatewayName(): string
    {
        return 'tap';
    }

    public function initiate(InitiatePaymentDTO $dto): array
    {
        $response = $this->post($this->cfg('base_url') . '/charges', [
            'amount'      => $dto->amount,
            'currency'    => $dto->currency,
            'reference'   => ['transaction' => $dto->trackId],
            'description' => $dto->description ?? 'Payment',
            'redirect'    => ['url' => $dto->successUrl],
        ], [
            'Authorization' => 'Bearer ' . $this->cfg('secret_key'),
        ]);

        return [
            'redirect_url' => $response['transaction']['url'] ?? '',
            'charge_id'    => $response['id'] ?? null,
            'track_id'     => $dto->trackId,
        ];
    }

    public function handleCallback(array $payload): GatewayResponseDTO
    {
        $chargeId = $payload['tap_id'] ?? $payload['id'] ?? null;

        $response = $this->get($this->cfg('base_url') . '/charges/' . $chargeId, [], [
            'Authorization' => 'Bearer ' . $this->cfg('secret_key'),
        ]);

        $captured = strtoupper((string) ($response['status'] ?? '')) === 'CAPTURED';

        return new GatewayResponseDTO(
            success:               $captured,
            status:                $captured ? TransactionStatus::Captured : TransactionStatus::Failed,
            gatewayTransactionId:  $response['id'] ?? null,
            trackId:               $response['reference']['transaction'] ?? null,
            referenceId:           $response['reference']['order'] ?? null,
            amount:                isset($response['amount']) ? (float) $response['amount'] : null,
            currency:              $response['currency'] ?? null,
            gatewayStatus:         $response['status'] ?? null,
            rawPayload:            $response,
        );
    }

    public function inquire(string $trackId, ?float $amount = null): GatewayResponseDTO
    {
        $response = $this->get(
            $this->cfg('base_url') . '/charges',
            ['reference[transaction]' => $trackId],
            ['Authorization' => 'Bearer ' . $this->cfg('secret_key')],
        );

        $charge   = $response['data'][0] ?? [];
        $captured = strtoupper((string) ($charge['status'] ?? '')) === 'CAPTURED';

        return new GatewayResponseDTO(
            success:       $captured,
            status:        $captured ? TransactionStatus::Captured : TransactionStatus::Unknown,
            gatewayStatus: $charge['status'] ?? null,
            rawPayload:    $charge,
        );
    }

    public function fetchTransactions(string $from, string $to, int $page = 1, int $perPage = 500): array
    {
        $response = $this->get($this->cfg('base_url') . '/charges', [
            'starting_after' => null,
            'limit'          => $perPage,
        ], [
            'Authorization' => 'Bearer ' . $this->cfg('secret_key'),
        ]);

        return $response['data'] ?? [];
    }
}

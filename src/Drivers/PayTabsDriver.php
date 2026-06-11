<?php

declare(strict_types=1);

namespace Mostafax\PaymentEngine\Drivers;

use Mostafax\PaymentEngine\DTOs\GatewayResponseDTO;
use Mostafax\PaymentEngine\DTOs\InitiatePaymentDTO;
use Mostafax\PaymentEngine\Enums\TransactionStatus;

final class PayTabsDriver extends AbstractPaymentDriver
{
    public function __construct()
    {
        parent::__construct('paytabs');
    }

    public function getGatewayName(): string
    {
        return 'paytabs';
    }

    public function initiate(InitiatePaymentDTO $dto): array
    {
        $response = $this->post($this->cfg('base_url') . '/payment/request', [
            'profile_id'       => $this->cfg('profile_id'),
            'tran_type'        => 'sale',
            'tran_class'       => 'ecom',
            'cart_id'          => $dto->trackId,
            'cart_amount'      => $dto->amount,
            'cart_currency'    => $dto->currency,
            'cart_description' => $dto->description ?? 'Payment',
            'callback'         => $dto->successUrl,
            'return'           => $dto->successUrl,
        ], [
            'Authorization' => $this->cfg('server_key'),
        ]);

        return [
            'redirect_url' => $response['redirect_url'] ?? '',
            'tran_ref'     => $response['tran_ref']     ?? null,
            'track_id'     => $dto->trackId,
        ];
    }

    public function handleCallback(array $payload): GatewayResponseDTO
    {
        $captured = strtolower((string) ($payload['respStatus'] ?? '')) === 'a';

        return new GatewayResponseDTO(
            success:               $captured,
            status:                $captured ? TransactionStatus::Captured : TransactionStatus::Failed,
            gatewayTransactionId:  $payload['tran_ref']  ?? null,
            trackId:               $payload['cart_id']   ?? null,
            referenceId:           $payload['payment_ref'] ?? null,
            amount:                isset($payload['cart_amount']) ? (float) $payload['cart_amount'] : null,
            currency:              $payload['cart_currency'] ?? null,
            gatewayStatus:         $payload['respStatus']   ?? null,
            rawPayload:            $payload,
        );
    }

    public function inquire(string $trackId, ?float $amount = null): GatewayResponseDTO
    {
        $response = $this->post($this->cfg('base_url') . '/payment/query', [
            'profile_id' => $this->cfg('profile_id'),
            'tran_ref'   => $trackId,
        ], [
            'Authorization' => $this->cfg('server_key'),
        ]);

        $captured = strtolower((string) ($response['payment_result']['response_status'] ?? '')) === 'a';

        return new GatewayResponseDTO(
            success:       $captured,
            status:        $captured ? TransactionStatus::Captured : TransactionStatus::Unknown,
            gatewayStatus: $response['payment_result']['response_status'] ?? null,
            rawPayload:    $response,
        );
    }

    public function fetchTransactions(string $from, string $to, int $page = 1, int $perPage = 500): array
    {
        return [];
    }
}

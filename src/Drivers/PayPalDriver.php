<?php

declare(strict_types=1);

namespace Mostafax\PaymentEngine\Drivers;

use Mostafax\PaymentEngine\DTOs\GatewayResponseDTO;
use Mostafax\PaymentEngine\DTOs\InitiatePaymentDTO;
use Mostafax\PaymentEngine\Enums\TransactionStatus;
use Mostafax\PaymentEngine\Exceptions\GatewayException;

final class PayPalDriver extends AbstractPaymentDriver
{
    private ?string $accessToken = null;

    public function __construct()
    {
        parent::__construct('paypal');
    }

    public function getGatewayName(): string
    {
        return 'paypal';
    }

    public function initiate(InitiatePaymentDTO $dto): array
    {
        $token    = $this->getAccessToken();
        $baseUrl  = $this->cfg('base_url');

        $response = $this->post($baseUrl . '/v2/checkout/orders', [
            'intent'         => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $dto->trackId,
                'amount'       => [
                    'currency_code' => $dto->currency,
                    'value'         => number_format($dto->amount, 2, '.', ''),
                ],
            ]],
            'application_context' => [
                'return_url' => $dto->successUrl,
                'cancel_url' => $dto->errorUrl,
            ],
        ], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $approveUrl = collect($response['links'] ?? [])
            ->firstWhere('rel', 'approve')['href'] ?? '';

        return [
            'redirect_url' => $approveUrl,
            'order_id'     => $response['id'] ?? null,
            'track_id'     => $dto->trackId,
        ];
    }

    public function handleCallback(array $payload): GatewayResponseDTO
    {
        $orderId  = $payload['token'] ?? $payload['order_id'] ?? null;
        $token    = $this->getAccessToken();
        $baseUrl  = $this->cfg('base_url');

        $capture  = $this->post(
            $baseUrl . "/v2/checkout/orders/{$orderId}/capture",
            [],
            ['Authorization' => 'Bearer ' . $token],
        );

        $unit     = $capture['purchase_units'][0]['payments']['captures'][0] ?? [];
        $captured = ($capture['status'] ?? '') === 'COMPLETED';

        return new GatewayResponseDTO(
            success:               $captured,
            status:                $captured ? TransactionStatus::Captured : TransactionStatus::Failed,
            gatewayTransactionId:  $unit['id']       ?? null,
            trackId:               $capture['purchase_units'][0]['reference_id'] ?? null,
            amount:                isset($unit['amount']['value']) ? (float) $unit['amount']['value'] : null,
            currency:              $unit['amount']['currency_code'] ?? null,
            gatewayStatus:         $capture['status'] ?? null,
            rawPayload:            $capture,
        );
    }

    public function inquire(string $trackId, ?float $amount = null): GatewayResponseDTO
    {
        $token    = $this->getAccessToken();
        $response = $this->get(
            $this->cfg('base_url') . "/v2/checkout/orders/{$trackId}",
            [],
            ['Authorization' => 'Bearer ' . $token],
        );

        $captured = ($response['status'] ?? '') === 'COMPLETED';

        return new GatewayResponseDTO(
            success:       $captured,
            status:        $captured ? TransactionStatus::Captured : TransactionStatus::Unknown,
            gatewayStatus: $response['status'] ?? null,
            rawPayload:    $response,
        );
    }

    public function fetchTransactions(string $from, string $to, int $page = 1, int $perPage = 500): array
    {
        return [];
    }

    private function getAccessToken(): string
    {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }

        $response = $this->request('POST', $this->cfg('base_url') . '/v1/oauth2/token', [
            'auth'        => [$this->cfg('client_id'), $this->cfg('client_secret')],
            'form_params' => ['grant_type' => 'client_credentials'],
            'headers'     => ['Content-Type' => 'application/x-www-form-urlencoded'],
        ]);

        if (empty($response['access_token'])) {
            throw new GatewayException('PayPal: failed to obtain access token.');
        }

        $this->accessToken = $response['access_token'];
        return $this->accessToken;
    }
}

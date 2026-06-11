<?php

declare(strict_types=1);

namespace Mostafax\PaymentEngine\Drivers;

use Mostafax\PaymentEngine\DTOs\GatewayResponseDTO;
use Mostafax\PaymentEngine\DTOs\InitiatePaymentDTO;
use Mostafax\PaymentEngine\Enums\TransactionStatus;
use Mostafax\PaymentEngine\Exceptions\WebhookException;

final class FawryDriver extends AbstractPaymentDriver
{
    public function __construct()
    {
        parent::__construct('fawry');
    }

    public function getGatewayName(): string
    {
        return 'fawry';
    }

    public function getSupportedCurrencies(): array
    {
        return ['EGP'];
    }

    public function initiate(InitiatePaymentDTO $dto): array
    {
        $merchantCode = (string) $this->cfg('merchant_code');
        $secureKey    = (string) $this->cfg('secure_key');
        $returnUrl    = (string) ($this->cfg('return_url') ?? $dto->successUrl);
        $customerId   = (string) ($dto->metadata['customer_id'] ?? 'guest');

        $chargeItems = [[
            'itemId'      => $dto->orderId ?? $dto->trackId,
            'description' => $dto->description ?? 'Payment',
            'price'       => round($dto->amount, 2),
            'quantity'    => 1,
        ]];

        $signature = $this->buildChargeSignature(
            merchantCode: $merchantCode,
            merchantRefNum: $dto->trackId,
            customerId: $customerId,
            returnUrl: $returnUrl,
            items: $chargeItems,
            secureKey: $secureKey,
        );

        $response = $this->post(
            $this->baseUrl() . '/ECommerceWeb/Fawry/payments/charge',
            [
                'merchantCode'           => $merchantCode,
                'merchantRefNum'         => $dto->trackId,
                'customerProfileId'      => $customerId,
                'customerName'           => (string) ($dto->metadata['customer_name'] ?? ''),
                'customerEmail'          => (string) ($dto->metadata['customer_email'] ?? ''),
                'customerMobile'         => (string) ($dto->metadata['customer_mobile'] ?? ''),
                'paymentExpiry'          => (int) (now()->addHours(2)->timestamp * 1000),
                'chargeItems'            => $chargeItems,
                'returnUrl'              => $returnUrl,
                'authCaptureModePayment' => false,
                'signature'              => $signature,
            ],
        );

        return [
            'redirect_url'     => $response['nextAction']['redirectUrl'] ?? $this->buildHostedUrl($merchantCode, $dto->trackId),
            'reference_number' => $response['referenceNumber'] ?? null,
            'payment_type'     => $response['type'] ?? null, // CARD | CASH_ON_DELIVERY
            'track_id'         => $dto->trackId,
        ];
    }

    public function handleCallback(array $payload): GatewayResponseDTO
    {
        $this->verifyCallbackSignature($payload);

        $status  = strtoupper((string) ($payload['paymentStatus'] ?? $payload['orderStatus'] ?? ''));
        $success = $status === 'PAID';

        return new GatewayResponseDTO(
            success:              $success,
            status:               $success ? TransactionStatus::Captured : TransactionStatus::Failed,
            gatewayTransactionId: isset($payload['referenceNumber']) ? (string) $payload['referenceNumber'] : null,
            trackId:              isset($payload['merchantRefNum']) ? (string) $payload['merchantRefNum'] : null,
            referenceId:          isset($payload['referenceNumber']) ? (string) $payload['referenceNumber'] : null,
            amount:               isset($payload['paymentAmount']) ? (float) $payload['paymentAmount'] : null,
            currency:             'EGP',
            gatewayStatus:        $status,
            rawPayload:           $payload,
        );
    }

    public function inquire(string $trackId, ?float $amount = null): GatewayResponseDTO
    {
        $merchantCode = (string) $this->cfg('merchant_code');
        $signature    = hash('sha256', $merchantCode . $trackId . $this->cfg('secure_key'));

        $response = $this->get(
            $this->baseUrl() . '/ECommerceWeb/Fawry/payments/status/v2',
            [
                'merchantCode'   => $merchantCode,
                'merchantRefNum' => $trackId,
                'signature'      => $signature,
            ],
        );

        $status  = strtoupper((string) ($response['paymentStatus'] ?? ''));
        $success = $status === 'PAID';

        return new GatewayResponseDTO(
            success:              $success,
            status:               $success ? TransactionStatus::Captured : TransactionStatus::Unknown,
            gatewayTransactionId: isset($response['referenceNumber']) ? (string) $response['referenceNumber'] : null,
            trackId:              $trackId,
            amount:               isset($response['paymentAmount']) ? (float) $response['paymentAmount'] : null,
            currency:             'EGP',
            gatewayStatus:        $status,
            rawPayload:           $response,
        );
    }

    // Fawry has no batch transaction fetch endpoint
    public function fetchTransactions(string $from, string $to, int $page = 1, int $perPage = 500): array
    {
        return [];
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function baseUrl(): string
    {
        return $this->isSandbox()
            ? 'https://atfawry.fawrystaging.com'
            : 'https://www.atfawry.com';
    }

    private function buildHostedUrl(string $merchantCode, string $merchantRefNum): string
    {
        return $this->baseUrl() . '/atfawry/process?' . http_build_query([
            'merchantCode'   => $merchantCode,
            'merchantRefNum' => $merchantRefNum,
        ]);
    }

    /**
     * SHA-256( merchantCode + merchantRefNum + customerId + returnUrl
     *          + concat(itemId + formattedPrice + quantity) + secureKey )
     */
    private function buildChargeSignature(
        string $merchantCode,
        string $merchantRefNum,
        string $customerId,
        string $returnUrl,
        array  $items,
        string $secureKey,
    ): string {
        $raw = $merchantCode . $merchantRefNum . $customerId . $returnUrl;

        foreach ($items as $item) {
            $raw .= $item['itemId']
                . number_format((float) $item['price'], 2, '.', '')
                . (string) $item['quantity'];
        }

        $raw .= $secureKey;

        return hash('sha256', $raw);
    }

    /**
     * Fawry signs callbacks with:
     * MD5( merchantCode + merchantRefNum + formattedAmount + orderStatus + referenceNumber + secureKey )
     */
    private function verifyCallbackSignature(array $payload): void
    {
        if (! $this->cfg('verify_signature', true)) {
            return;
        }

        $expected = md5(
            $this->cfg('merchant_code') .
            ($payload['merchantRefNum'] ?? '') .
            number_format((float) ($payload['paymentAmount'] ?? 0), 2, '.', '') .
            ($payload['orderStatus'] ?? $payload['paymentStatus'] ?? '') .
            ($payload['referenceNumber'] ?? '') .
            $this->cfg('secure_key'),
        );

        $received = strtolower((string) ($payload['messageSignature'] ?? ''));

        if (! hash_equals($expected, $received)) {
            throw new WebhookException('Invalid Fawry callback signature.');
        }
    }
}

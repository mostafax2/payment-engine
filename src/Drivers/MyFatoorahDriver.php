<?php

declare(strict_types=1);

namespace Mostafax\PaymentEngine\Drivers;

use Mostafax\PaymentEngine\DTOs\GatewayResponseDTO;
use Mostafax\PaymentEngine\DTOs\InitiatePaymentDTO;
use Mostafax\PaymentEngine\Enums\TransactionStatus;

final class MyFatoorahDriver extends AbstractPaymentDriver
{
    private const INITIATE_PATH    = '/v2/InitiateSession';
    private const EXECUTE_PATH     = '/v2/ExecutePayment';
    private const STATUS_PATH      = '/v2/GetPaymentStatus';
    private const TRANSACTIONS_PATH= '/v2/GetPaymentByTrackId';

    public function __construct()
    {
        parent::__construct('myfatoorah');
    }

    public function getGatewayName(): string
    {
        return 'myfatoorah';
    }

    public function initiate(InitiatePaymentDTO $dto): array
    {
        $baseUrl = $this->cfg('base_url');

        $session = $this->post($baseUrl . self::INITIATE_PATH, [], [
            'Authorization' => 'Bearer ' . $this->cfg('api_key'),
        ]);

        $body = [
            'SessionId'         => $session['Data']['SessionId'] ?? null,
            'InvoiceValue'      => $dto->amount,
            'CurrencyIso'       => $dto->currency,
            'DisplayCurrencyIso'=> $dto->currency,
            'CustomerReference' => $dto->trackId,
            'CallBackUrl'       => $dto->successUrl,
            'ErrorUrl'          => $dto->errorUrl,
            'Language'          => 'en',
        ];

        $response = $this->post($baseUrl . self::EXECUTE_PATH, $body, [
            'Authorization' => 'Bearer ' . $this->cfg('api_key'),
        ]);

        return [
            'redirect_url' => $response['Data']['PaymentURL'] ?? '',
            'invoice_id'   => $response['Data']['InvoiceId']  ?? null,
            'track_id'     => $dto->trackId,
        ];
    }

    public function handleCallback(array $payload): GatewayResponseDTO
    {
        $paymentId = $payload['paymentId'] ?? $payload['InvoiceId'] ?? null;

        $response = $this->post(
            $this->cfg('base_url') . self::STATUS_PATH,
            ['Key' => $paymentId, 'KeyType' => 'PaymentId'],
            ['Authorization' => 'Bearer ' . $this->cfg('api_key')],
        );

        $data     = $response['Data']['InvoiceTransactions'][0] ?? [];
        $captured = strtoupper((string) ($data['TransactionStatus'] ?? '')) === 'SUCCSS';

        return new GatewayResponseDTO(
            success:               $captured,
            status:                $captured ? TransactionStatus::Captured : TransactionStatus::Failed,
            gatewayTransactionId:  (string) ($data['TransactionId'] ?? ''),
            trackId:               (string) ($response['Data']['CustomerReference'] ?? ''),
            referenceId:           $data['ReferenceId'] ?? null,
            amount:                isset($data['TransactionValue']) ? (float) $data['TransactionValue'] : null,
            currency:              $data['Currency'] ?? $this->cfg('currency'),
            gatewayStatus:         $data['TransactionStatus'] ?? null,
            rawPayload:            $response,
        );
    }

    public function inquire(string $trackId, ?float $amount = null): GatewayResponseDTO
    {
        $response = $this->post(
            $this->cfg('base_url') . self::TRANSACTIONS_PATH,
            ['Key' => $trackId, 'KeyType' => 'CustomerReference'],
            ['Authorization' => 'Bearer ' . $this->cfg('api_key')],
        );

        $data     = $response['Data']['InvoiceTransactions'][0] ?? [];
        $captured = strtoupper((string) ($data['TransactionStatus'] ?? '')) === 'SUCCSS';

        return new GatewayResponseDTO(
            success:       $captured,
            status:        $captured ? TransactionStatus::Captured : TransactionStatus::Unknown,
            gatewayStatus: $data['TransactionStatus'] ?? null,
            rawPayload:    $response,
        );
    }

    public function fetchTransactions(string $from, string $to, int $page = 1, int $perPage = 500): array
    {
        $response = $this->post(
            $this->cfg('base_url') . '/v2/GetInvoiceTransactions',
            ['From' => $from, 'To' => $to, 'Page' => $page, 'PageSize' => $perPage],
            ['Authorization' => 'Bearer ' . $this->cfg('api_key')],
        );

        return $response['Data'] ?? [];
    }
}

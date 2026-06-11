<?php

declare(strict_types=1);

namespace Mostafax\PaymentEngine\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Mostafax\PaymentEngine\Contracts\PaymentDriverInterface;
use Mostafax\PaymentEngine\DTOs\GatewayResponseDTO;
use Mostafax\PaymentEngine\DTOs\InitiatePaymentDTO;
use Mostafax\PaymentEngine\Enums\TransactionStatus;
use Mostafax\PaymentEngine\Exceptions\GatewayException;

final class KnetDriver extends AbstractPaymentDriver implements PaymentDriverInterface
{
    public function __construct()
    {
        parent::__construct('knet');
    }

    public function getGatewayName(): string
    {
        return 'knet';
    }

    // -----------------------------------------------------------------------
    // Initiate
    // -----------------------------------------------------------------------

    public function initiate(InitiatePaymentDTO $dto): array
    {
        $param = $this->buildRequestString($dto);
        $key   = $this->cfg('resource_key');

        $trandata = $this->encryptAES($param, $key)
            . '&tranportalId=' . $this->cfg('transport_id')
            . '&responseURL=' . $dto->successUrl
            . '&errorURL='    . $dto->errorUrl;

        if (! $trandata) {
            throw new GatewayException('KNET: failed to encrypt payment request.');
        }

        $baseUrl = $this->isSandbox()
            ? $this->cfg('test_url')
            : $this->cfg('live_url');

        return [
            'redirect_url' => $baseUrl . '&trandata=' . $trandata,
            'track_id'     => $dto->trackId,
        ];
    }

    // -----------------------------------------------------------------------
    // Callback
    // -----------------------------------------------------------------------

    public function handleCallback(array $payload): GatewayResponseDTO
    {
        $raw = $payload['trandata'] ?? '';
        $key = $this->cfg('resource_key');

        $decoded = $raw !== '' ? $this->decrypt($raw, $key) : null;

        if ($decoded !== null && $decoded !== false) {
            parse_str((string) $decoded, $data);
        } else {
            $data = $payload;
        }

        $result   = strtoupper((string) ($data['result']   ?? ''));
        $captured = $result === 'CAPTURED';

        return new GatewayResponseDTO(
            success:               $captured,
            status:                $captured ? TransactionStatus::Captured : TransactionStatus::Failed,
            gatewayTransactionId:  $data['paymentid'] ?? null,
            trackId:               $data['trackid']   ?? null,
            referenceId:           $data['ref']       ?? null,
            authCode:              $data['auth']      ?? null,
            amount:                isset($data['amt']) ? (float) $data['amt'] : null,
            currency:              $this->cfg('currency', 'KWD'),
            gatewayStatus:         $result,
            postDate:              $data['postdate']  ?? null,
            rawPayload:            $data,
            errorMessage:          $captured ? null : ($data['result'] ?? 'Payment failed'),
        );
    }

    // -----------------------------------------------------------------------
    // Inquiry (Transaction Check)
    // -----------------------------------------------------------------------

    public function inquire(string $trackId, ?float $amount = null): GatewayResponseDTO
    {
        $url = $this->cfg('inquiry_url');
        $xml = implode('', [
            '<id>',       $this->cfg('transport_id'), '</id>',
            '<password>', $this->cfg('password'),     '</password>',
            '<action>8</action>',
            '<amt>',      $amount ?? '0', '</amt>',
            '<transid>',  $trackId, '</transid>',
            '<udf5>TrackID</udf5>',
            '<trackid>',  $trackId, '</trackid>',
        ]);

        try {
            $client   = new Client(['verify' => false, 'timeout' => 20]);
            $response = $client->request('POST', $url, [
                RequestOptions::BODY    => $xml,
                RequestOptions::HEADERS => [
                    'Content-Type'   => 'application/xml',
                    'Content-length' => (string) strlen($xml),
                ],
            ]);

            $raw  = '<response>' . $response->getBody()->getContents() . '</response>';
            $xml  = simplexml_load_string($raw);
            $data = json_decode((string) json_encode($xml), associative: true) ?? [];

            $captured = strtoupper((string) ($data['result'] ?? '')) === 'SUCCESS';

            return new GatewayResponseDTO(
                success:       $captured,
                status:        $captured ? TransactionStatus::Captured : TransactionStatus::Unknown,
                gatewayStatus: $data['result'] ?? null,
                rawPayload:    $data,
            );
        } catch (\Throwable $e) {
            throw new GatewayException('KNET inquiry failed: ' . $e->getMessage(), previous: $e);
        }
    }

    // -----------------------------------------------------------------------
    // Fetch transactions for reconciliation / backfill
    // -----------------------------------------------------------------------

    public function fetchTransactions(string $from, string $to, int $page = 1, int $perPage = 500): array
    {
        // KNET does not provide a batch inquiry API. Return empty so the
        // reconciliation engine falls back to internal-only comparison.
        return [];
    }

    // -----------------------------------------------------------------------
    // AES-128-CBC helpers (ported from original knet.php)
    // -----------------------------------------------------------------------

    private function buildRequestString(InitiatePaymentDTO $dto): string
    {
        $udf = array_values(array_slice(array_pad($dto->metadata, 5, ''), 0, 5));

        return implode('&', [
            'id='          . $this->cfg('transport_id'),
            'password='    . $this->cfg('password'),
            'action='      . $this->cfg('action', '1'),
            'langid='      . $this->cfg('language', 'ENG'),
            'currencycode='. $dto->currency,
            'amt='         . number_format($dto->amount, 3, '.', ''),
            'responseURL=' . $dto->successUrl,
            'errorURL='    . $dto->errorUrl,
            'trackid='     . $dto->trackId,
            'udf1='        . ($udf[0] ?? ''),
            'udf2='        . ($udf[1] ?? ''),
            'udf3='        . ($udf[2] ?? ''),
            'udf4='        . ($udf[3] ?? ''),
            'udf5='        . ($udf[4] ?? ''),
        ]);
    }

    private function encryptAES(string $str, string $key): string
    {
        $padded    = $this->pkcs5Pad($str);
        $encrypted = openssl_encrypt($padded, 'AES-128-CBC', $key, OPENSSL_ZERO_PADDING, $key);

        if ($encrypted === false) {
            return '';
        }

        $bytes     = unpack('C*', (string) base64_decode($encrypted));
        $hex       = bin2hex(implode('', array_map('chr', $bytes)));
        return urlencode($hex);
    }

    private function decrypt(string $code, string $key): string|false
    {
        $bytes     = unpack('C*', (string) hex2bin(trim($code)));
        $binary    = implode('', array_map('chr', $bytes));
        $b64       = base64_encode($binary);
        $decrypted = openssl_decrypt($b64, 'AES-128-CBC', $key, OPENSSL_ZERO_PADDING, $key);

        if ($decrypted === false) {
            return false;
        }

        return $this->pkcs5Unpad($decrypted);
    }

    private function pkcs5Pad(string $text): string
    {
        $blocksize = 16;
        $pad       = $blocksize - (strlen($text) % $blocksize);
        return $text . str_repeat(chr($pad), $pad);
    }

    private function pkcs5Unpad(string $text): string|false
    {
        $pad = ord($text[strlen($text) - 1]);
        if ($pad > strlen($text)) {
            return false;
        }
        if (strspn($text, chr($pad), strlen($text) - $pad) !== $pad) {
            return false;
        }
        return substr($text, 0, -$pad);
    }
}

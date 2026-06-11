<?php

declare(strict_types=1);

namespace Mostafax\PaymentEngine\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Mostafax\PaymentEngine\Contracts\PaymentDriverInterface;
use Mostafax\PaymentEngine\Exceptions\GatewayException;

abstract class AbstractPaymentDriver implements PaymentDriverInterface
{
    protected Client $http;
    protected array  $config;

    public function __construct(string $gateway)
    {
        $this->config = config("payment-engine.gateways.{$gateway}", []);
        $this->http   = new Client([
            'timeout'         => 30,
            'connect_timeout' => 10,
            'verify'          => true,
            'headers'         => $this->defaultHeaders(),
        ]);
    }

    protected function defaultHeaders(): array
    {
        return [
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    protected function get(string $url, array $query = [], array $headers = []): array
    {
        return $this->request('GET', $url, ['query' => $query, 'headers' => $headers]);
    }

    protected function post(string $url, array $body = [], array $headers = []): array
    {
        return $this->request('POST', $url, ['json' => $body, 'headers' => $headers]);
    }

    protected function request(string $method, string $url, array $options = []): array
    {
        try {
            $response = $this->http->request($method, $url, $options);
            $body     = $response->getBody()->getContents();
            return json_decode($body, associative: true) ?? [];
        } catch (GuzzleException $e) {
            throw new GatewayException(
                "Gateway [{$this->getGatewayName()}] HTTP error: " . $e->getMessage(),
                previous: $e,
            );
        }
    }

    public function getSupportedCurrencies(): array
    {
        return [$this->config['currency'] ?? 'KWD'];
    }

    protected function isSandbox(): bool
    {
        return (bool) ($this->config['sandbox'] ?? true);
    }

    protected function cfg(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }
}

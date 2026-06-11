<?php

declare(strict_types=1);

namespace Mostafax\PaymentEngine\Contracts;

use Mostafax\PaymentEngine\DTOs\GatewayResponseDTO;
use Mostafax\PaymentEngine\DTOs\InitiatePaymentDTO;

interface PaymentDriverInterface
{
    /** Build the gateway redirect URL/payload for a new payment. */
    public function initiate(InitiatePaymentDTO $dto): array;

    /** Handle a success/error callback from the gateway (decrypts if needed). */
    public function handleCallback(array $payload): GatewayResponseDTO;

    /** Query the gateway directly for a transaction's current status. */
    public function inquire(string $trackId, ?float $amount = null): GatewayResponseDTO;

    /** Fetch a page of gateway transactions for reconciliation / backfill. */
    public function fetchTransactions(string $from, string $to, int $page = 1, int $perPage = 500): array;

    public function getGatewayName(): string;

    public function getSupportedCurrencies(): array;
}

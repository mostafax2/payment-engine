<?php

declare(strict_types=1);

namespace Mostafax\PaymentEngine\Contracts;

use Illuminate\Support\Collection;
use Mostafax\PaymentEngine\DTOs\GatewayResponseDTO;
use Mostafax\PaymentEngine\DTOs\InitiatePaymentDTO;
use Mostafax\PaymentEngine\Enums\TransactionEventType;
use Mostafax\PaymentEngine\Enums\TransactionStatus;
use Mostafax\PaymentEngine\Models\Transaction;

interface TransactionRepositoryInterface
{
    public function create(InitiatePaymentDTO $dto): Transaction;

    public function findByTrackId(string $trackId): ?Transaction;

    public function findByGatewayTransactionId(string $id): ?Transaction;

    public function applyGatewayResponse(Transaction $transaction, GatewayResponseDTO $response): Transaction;

    public function recordEvent(
        Transaction $transaction,
        TransactionEventType $type,
        array $payload = [],
        ?string $actor = 'system',
    ): void;

    public function forReconciliation(string $gateway, string $from, string $to): Collection;

    public function pendingOlderThan(int $minutes): Collection;
}

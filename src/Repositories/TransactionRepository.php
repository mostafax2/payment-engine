<?php

declare(strict_types=1);

namespace Mostafax\PaymentEngine\Repositories;

use Illuminate\Support\Collection;
use Mostafax\PaymentEngine\Contracts\TransactionRepositoryInterface;
use Mostafax\PaymentEngine\DTOs\GatewayResponseDTO;
use Mostafax\PaymentEngine\DTOs\InitiatePaymentDTO;
use Mostafax\PaymentEngine\Enums\TransactionEventType;
use Mostafax\PaymentEngine\Enums\TransactionStatus;
use Mostafax\PaymentEngine\Models\Transaction;
use Mostafax\PaymentEngine\Models\TransactionEvent;

final class TransactionRepository implements TransactionRepositoryInterface
{
    public function create(InitiatePaymentDTO $dto): Transaction
    {
        $transaction = Transaction::create([
            'gateway'    => $dto->gateway,
            'track_id'   => $dto->trackId,
            'order_id'   => $dto->orderId,
            'tenant_id'  => $dto->tenantId,
            'amount'     => $dto->amount,
            'currency'   => $dto->currency,
            'status'     => TransactionStatus::Initiated,
            'metadata'   => $dto->metadata ?: null,
            'initiated_at'=> now(),
        ]);

        $this->recordEvent($transaction, TransactionEventType::Initiated, $dto->toArray());

        return $transaction;
    }

    public function findByTrackId(string $trackId): ?Transaction
    {
        return Transaction::where('track_id', $trackId)->first();
    }

    public function findByGatewayTransactionId(string $id): ?Transaction
    {
        return Transaction::where('gateway_transaction_id', $id)->first();
    }

    public function applyGatewayResponse(Transaction $transaction, GatewayResponseDTO $response): Transaction
    {
        $fromStatus = $transaction->status;

        $updates = [
            'status'                 => $response->status,
            'gateway_status'         => $response->gatewayStatus,
            'gateway_response'       => $response->toArray(),
        ];

        if ($response->gatewayTransactionId !== null) {
            $updates['gateway_transaction_id'] = $response->gatewayTransactionId;
        }
        if ($response->referenceId !== null) {
            $updates['reference_id'] = $response->referenceId;
        }
        if ($response->authCode !== null) {
            $updates['auth_code'] = $response->authCode;
        }
        if ($response->postDate !== null) {
            $updates['post_date'] = $response->postDate;
        }
        if ($response->status === TransactionStatus::Captured) {
            $updates['captured_at'] = now();
        }
        if ($response->status === TransactionStatus::Failed) {
            $updates['failed_at'] = now();
        }

        $transaction->update($updates);
        $transaction->refresh();

        $this->recordEvent(
            transaction: $transaction,
            type:        TransactionEventType::StatusUpdated,
            payload:     $response->toArray(),
            actor:       'gateway_callback',
        );

        return $transaction;
    }

    public function recordEvent(
        Transaction $transaction,
        TransactionEventType $type,
        array $payload = [],
        ?string $actor = 'system',
    ): void {
        TransactionEvent::create([
            'transaction_id' => $transaction->id,
            'event_type'     => $type,
            'from_status'    => null,
            'to_status'      => $transaction->status instanceof TransactionStatus
                ? $transaction->status->value
                : $transaction->status,
            'actor'          => $actor,
            'payload'        => $payload ?: null,
        ]);
    }

    public function forReconciliation(string $gateway, string $from, string $to): Collection
    {
        return Transaction::forGateway($gateway)
            ->createdBetween($from, $to)
            ->get(['id', 'track_id', 'gateway_transaction_id', 'amount', 'currency', 'status', 'created_at']);
    }

    public function pendingOlderThan(int $minutes): Collection
    {
        return Transaction::pending()
            ->where('initiated_at', '<', now()->subMinutes($minutes))
            ->get();
    }
}

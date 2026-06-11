<?php

declare(strict_types=1);

namespace Mostafax\PaymentEngine\Services;

use Mostafax\PaymentEngine\Contracts\PaymentDriverInterface;
use Mostafax\PaymentEngine\Contracts\TransactionRepositoryInterface;
use Mostafax\PaymentEngine\DTOs\GatewayResponseDTO;
use Mostafax\PaymentEngine\DTOs\InitiatePaymentDTO;
use Mostafax\PaymentEngine\Enums\TransactionEventType;
use Mostafax\PaymentEngine\Enums\TransactionStatus;
use Mostafax\PaymentEngine\Events\PaymentCaptured;
use Mostafax\PaymentEngine\Events\PaymentFailed;
use Mostafax\PaymentEngine\Events\PaymentInitiated;
use Mostafax\PaymentEngine\Exceptions\PaymentException;
use Mostafax\PaymentEngine\Models\Transaction;

final class PaymentManager
{
    /** @var array<string, PaymentDriverInterface> */
    private array $driverCache = [];

    public function __construct(
        private readonly TransactionRepositoryInterface $repository,
    ) {}

    // -----------------------------------------------------------------------
    // Initiate a new payment
    // -----------------------------------------------------------------------

    public function initiate(InitiatePaymentDTO $dto): array
    {
        $driver      = $this->driver($dto->gateway);
        $transaction = $this->repository->create($dto);

        try {
            $result = $driver->initiate($dto);

            $this->repository->recordEvent(
                $transaction,
                TransactionEventType::RedirectedToGateway,
                ['redirect_url' => $result['redirect_url'] ?? null],
            );

            event(new PaymentInitiated($transaction, $dto));

            return array_merge($result, [
                'transaction_ulid' => $transaction->ulid,
                'track_id'         => $transaction->track_id,
            ]);
        } catch (\Throwable $e) {
            $this->repository->recordEvent($transaction, TransactionEventType::StatusUpdated, [
                'error' => $e->getMessage(),
            ], 'system_error');

            throw new PaymentException("Payment initiation failed: {$e->getMessage()}", previous: $e);
        }
    }

    // -----------------------------------------------------------------------
    // Handle gateway callback (success / error)
    // -----------------------------------------------------------------------

    public function handleCallback(string $gateway, array $payload): Transaction
    {
        $driver   = $this->driver($gateway);
        $response = $driver->handleCallback($payload);

        $transaction = $this->repository->findByTrackId($response->trackId ?? '')
            ?? $this->repository->findByGatewayTransactionId($response->gatewayTransactionId ?? '');

        if ($transaction === null) {
            throw new PaymentException("Transaction not found for callback. Track ID: {$response->trackId}");
        }

        // Idempotency — ignore if already terminal
        if ($transaction->isTerminal()) {
            return $transaction;
        }

        $this->repository->recordEvent(
            $transaction,
            TransactionEventType::CallbackReceived,
            $payload,
            'gateway_callback',
        );

        $transaction = $this->repository->applyGatewayResponse($transaction, $response);

        $this->dispatchStatusEvent($transaction, $response);

        return $transaction;
    }

    // -----------------------------------------------------------------------
    // Inquire a transaction status directly from the gateway
    // -----------------------------------------------------------------------

    public function inquire(string $trackId, string $gateway, ?float $amount = null): GatewayResponseDTO
    {
        $transaction = $this->repository->findByTrackId($trackId);
        $driver      = $this->driver($gateway);
        $response    = $driver->inquire($trackId, $amount);

        if ($transaction !== null) {
            $this->repository->recordEvent(
                $transaction,
                TransactionEventType::StatusChecked,
                $response->toArray(),
            );

            if ($transaction->isPending() && $response->status->isTerminal()) {
                $this->repository->applyGatewayResponse($transaction, $response);
                $this->dispatchStatusEvent($transaction, $response);
            }
        }

        return $response;
    }

    // -----------------------------------------------------------------------
    // Driver factory with caching
    // -----------------------------------------------------------------------

    public function driver(string $gateway): PaymentDriverInterface
    {
        if (! isset($this->driverCache[$gateway])) {
            $config = config("payment-engine.gateways.{$gateway}");

            if (empty($config['driver'])) {
                throw new PaymentException("Payment gateway [{$gateway}] is not configured.");
            }

            $this->driverCache[$gateway] = app($config['driver']);
        }

        return $this->driverCache[$gateway];
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    private function dispatchStatusEvent(Transaction $transaction, GatewayResponseDTO $response): void
    {
        match ($transaction->status) {
            TransactionStatus::Captured => event(new PaymentCaptured($transaction, $response)),
            TransactionStatus::Failed   => event(new PaymentFailed($transaction, $response)),
            default                     => null,
        };
    }
}

<?php

declare(strict_types=1);

namespace Mostafax\PaymentEngine\Services;

use Illuminate\Support\Facades\Log;
use Mostafax\PaymentEngine\Enums\TransactionEventType;
use Mostafax\PaymentEngine\Enums\TransactionStatus;
use Mostafax\PaymentEngine\Events\PaymentRecovered;
use Mostafax\PaymentEngine\Models\Transaction;
use Mostafax\PaymentEngine\Repositories\TransactionRepository;

final class RecoveryEngine
{
    public function __construct(
        private readonly TransactionRepository $txRepo,
        private readonly PaymentManager        $paymentManager,
    ) {}

    // -----------------------------------------------------------------------
    // Scan for stale pending transactions and attempt inquiry-based recovery
    // -----------------------------------------------------------------------

    public function recoverStale(int $olderThanMinutes = 30, int $maxPerRun = 1000): array
    {
        $pending = $this->txRepo->pendingOlderThan($olderThanMinutes)->take($maxPerRun);
        $stats   = ['total' => 0, 'recovered' => 0, 'still_pending' => 0, 'failed' => 0];

        foreach ($pending as $transaction) {
            $stats['total']++;

            try {
                $recovered = $this->recoverOne($transaction);

                if ($recovered) {
                    $stats['recovered']++;
                } else {
                    $stats['still_pending']++;
                }
            } catch (\Throwable $e) {
                $stats['failed']++;
                Log::warning("PaymentEngine recovery failed for {$transaction->track_id}: {$e->getMessage()}");
            }
        }

        return $stats;
    }

    // -----------------------------------------------------------------------
    // Recover a single transaction by querying its gateway
    // -----------------------------------------------------------------------

    public function recoverOne(Transaction $transaction): bool
    {
        $this->txRepo->recordEvent(
            $transaction,
            TransactionEventType::RecoveryAttempted,
            ['reason' => 'stale_pending'],
        );

        try {
            $response = $this->paymentManager->inquire(
                $transaction->track_id,
                $transaction->gateway,
                $transaction->amount,
            );

            if ($response->status->isTerminal()) {
                $this->txRepo->applyGatewayResponse($transaction, $response);
                $transaction->update(['recovered_at' => now()]);

                $this->txRepo->recordEvent(
                    $transaction,
                    TransactionEventType::Recovered,
                    $response->toArray(),
                );

                if (config('payment-engine.recovery.notify_on_recovery', true)) {
                    event(new PaymentRecovered($transaction, $response));
                }

                return true;
            }

            return false;
        } catch (\Throwable $e) {
            $this->txRepo->recordEvent(
                $transaction,
                TransactionEventType::RecoveryAttempted,
                ['error' => $e->getMessage()],
                'recovery_engine_error',
            );
            throw $e;
        }
    }

    // -----------------------------------------------------------------------
    // Rebuild missing transaction from gateway data (backfill scenario)
    // -----------------------------------------------------------------------

    public function rebuildFromGateway(string $gateway, array $gatewayPayload, ?string $tenantId = null): Transaction
    {
        $driver   = $this->paymentManager->driver($gateway);
        $response = $driver->handleCallback($gatewayPayload);

        $trackId     = $response->trackId ?? ('recovered_' . uniqid());
        $transaction = Transaction::create([
            'gateway'                => $gateway,
            'track_id'               => $trackId,
            'tenant_id'              => $tenantId,
            'amount'                 => $response->amount ?? 0,
            'currency'               => $response->currency ?? 'KWD',
            'status'                 => $response->status,
            'gateway_status'         => $response->gatewayStatus,
            'gateway_transaction_id' => $response->gatewayTransactionId,
            'reference_id'           => $response->referenceId,
            'auth_code'              => $response->authCode,
            'gateway_response'       => $response->toArray(),
            'initiated_at'           => now(),
            'recovered_at'           => now(),
            'captured_at'            => $response->status === TransactionStatus::Captured ? now() : null,
        ]);

        $this->txRepo->recordEvent($transaction, TransactionEventType::BackfillSynced, $gatewayPayload, 'backfill_engine');

        event(new PaymentRecovered($transaction, $response));

        return $transaction;
    }
}

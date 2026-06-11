<?php

declare(strict_types=1);

namespace Mostafax\PaymentEngine\Services;

use Illuminate\Support\Facades\Log;
use Mostafax\PaymentEngine\Enums\TransactionStatus;
use Mostafax\PaymentEngine\Models\Transaction;
use Mostafax\PaymentEngine\Repositories\TransactionRepository;

final class BackfillEngine
{
    public function __construct(
        private readonly TransactionRepository $txRepo,
        private readonly PaymentManager        $paymentManager,
        private readonly RecoveryEngine        $recoveryEngine,
    ) {}

    // -----------------------------------------------------------------------
    // Full sync: fetch all gateway transactions in range and upsert internally
    // -----------------------------------------------------------------------

    public function sync(string $gateway, string $from, string $to, ?string $tenantId = null): array
    {
        $driver      = $this->paymentManager->driver($gateway);
        $gatewayTxns = $driver->fetchTransactions($from, $to);

        $stats = ['total' => 0, 'created' => 0, 'skipped' => 0, 'errors' => 0];

        foreach ($gatewayTxns as $gtx) {
            $stats['total']++;

            try {
                $trackId = $this->resolveTrackId($gateway, $gtx);

                if (Transaction::where('track_id', $trackId)->exists()) {
                    $stats['skipped']++;
                    continue;
                }

                $this->recoveryEngine->rebuildFromGateway($gateway, $gtx, $tenantId);
                $stats['created']++;
            } catch (\Throwable $e) {
                $stats['errors']++;
                Log::warning("BackfillEngine error for {$gateway}: {$e->getMessage()}");
            }
        }

        return $stats;
    }

    // -----------------------------------------------------------------------
    // Sync only missing transactions (gap-fill)
    // -----------------------------------------------------------------------

    public function syncMissing(string $gateway, string $from, string $to, ?string $tenantId = null): array
    {
        $driver      = $this->paymentManager->driver($gateway);
        $gatewayTxns = $driver->fetchTransactions($from, $to);
        $internal    = $this->txRepo->forReconciliation($gateway, $from, $to)->keyBy('track_id');

        $missing = array_filter(
            $gatewayTxns,
            fn($gtx) => ! $internal->has($this->resolveTrackId($gateway, $gtx)),
        );

        $stats = ['missing' => count($missing), 'created' => 0, 'errors' => 0];

        foreach ($missing as $gtx) {
            try {
                $this->recoveryEngine->rebuildFromGateway($gateway, $gtx, $tenantId);
                $stats['created']++;
            } catch (\Throwable $e) {
                $stats['errors']++;
                Log::warning("BackfillEngine syncMissing error: {$e->getMessage()}");
            }
        }

        return $stats;
    }

    private function resolveTrackId(string $gateway, mixed $tx): string
    {
        return (string) match ($gateway) {
            'myfatoorah' => $tx['CustomerReference'] ?? '',
            'tap'        => $tx['reference']['transaction'] ?? '',
            'stripe'     => $tx['client_reference_id'] ?? '',
            default      => $tx['trackid'] ?? $tx['track_id'] ?? '',
        };
    }
}

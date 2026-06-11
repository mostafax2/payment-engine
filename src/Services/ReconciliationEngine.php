<?php

declare(strict_types=1);

namespace Mostafax\PaymentEngine\Services;

use Mostafax\PaymentEngine\Contracts\ReconciliationInterface;
use Mostafax\PaymentEngine\DTOs\ReconciliationResultDTO;
use Mostafax\PaymentEngine\Enums\ReconciliationStatus;
use Mostafax\PaymentEngine\Enums\TransactionStatus;
use Mostafax\PaymentEngine\Events\PaymentReconciled;
use Mostafax\PaymentEngine\Exceptions\ReconciliationException;
use Mostafax\PaymentEngine\Models\ReconciliationItem;
use Mostafax\PaymentEngine\Models\ReconciliationReport;
use Mostafax\PaymentEngine\Repositories\TransactionRepository;

final class ReconciliationEngine implements ReconciliationInterface
{
    private float $tolerance;

    public function __construct(
        private readonly TransactionRepository $txRepo,
        private readonly PaymentManager        $paymentManager,
    ) {
        $this->tolerance = (float) config('payment-engine.reconciliation.amount_tolerance', 0.01);
    }

    // -----------------------------------------------------------------------
    // Main entry point
    // -----------------------------------------------------------------------

    public function run(string $gateway, string $from, string $to, ?string $tenantId = null): ReconciliationResultDTO
    {
        $report = ReconciliationReport::create([
            'gateway'    => $gateway,
            'tenant_id'  => $tenantId,
            'period_from'=> $from,
            'period_to'  => $to,
            'status'     => ReconciliationStatus::Running,
            'run_by'     => 'system',
        ]);

        try {
            $result = $this->reconcile($gateway, $from, $to, $report);
            $this->updateReport($report, $result);
            $report->markCompleted();

            event(new PaymentReconciled($report, $result));

            return $result;
        } catch (\Throwable $e) {
            $report->markFailed($e->getMessage());
            throw new ReconciliationException("Reconciliation failed: {$e->getMessage()}", previous: $e);
        }
    }

    // -----------------------------------------------------------------------
    // Core reconciliation logic
    // -----------------------------------------------------------------------

    private function reconcile(string $gateway, string $from, string $to, ReconciliationReport $report): ReconciliationResultDTO
    {
        // 1. Fetch internal transactions
        $internal = $this->txRepo->forReconciliation($gateway, $from, $to);
        $internalMap = $internal->keyBy('track_id');

        // 2. Fetch gateway transactions
        $gatewayTxns = $this->fetchGatewayTransactions($gateway, $from, $to);
        $gatewayMap  = collect($gatewayTxns)->keyBy(fn($t) => $this->resolveGatewayTrackId($gateway, $t));

        // 3. Compare
        $matched           = 0;
        $missingInInternal = [];
        $missingInGateway  = [];
        $amountMismatches  = [];
        $statusMismatches  = [];

        // Gateway → Internal check
        foreach ($gatewayMap as $trackId => $gatewayTx) {
            $internalTx = $internalMap->get($trackId);

            if ($internalTx === null) {
                $missingInInternal[] = [
                    'track_id'              => $trackId,
                    'gateway_transaction_id'=> $this->resolveGatewayTxId($gateway, $gatewayTx),
                    'gateway_amount'        => $this->resolveGatewayAmount($gateway, $gatewayTx),
                    'gateway_status'        => $this->resolveGatewayStatus($gateway, $gatewayTx),
                ];
                continue;
            }

            $matched++;

            // Amount check
            $gwAmount  = (float) $this->resolveGatewayAmount($gateway, $gatewayTx);
            $intAmount = (float) $internalTx->amount;
            if (abs($gwAmount - $intAmount) > $this->tolerance) {
                $amountMismatches[] = [
                    'track_id'       => $trackId,
                    'gateway_amount' => $gwAmount,
                    'internal_amount'=> $intAmount,
                    'diff'           => abs($gwAmount - $intAmount),
                ];
            }

            // Status check
            $gwStatus  = strtolower((string) $this->resolveGatewayStatus($gateway, $gatewayTx));
            $intStatus = $internalTx->status instanceof TransactionStatus
                ? $internalTx->status->value
                : (string) $internalTx->status;

            if ($gwStatus === 'captured' && $intStatus !== TransactionStatus::Captured->value) {
                $statusMismatches[] = [
                    'track_id'       => $trackId,
                    'gateway_status' => $gwStatus,
                    'internal_status'=> $intStatus,
                ];
            }
        }

        // Internal → Gateway check (missing in gateway)
        foreach ($internalMap as $trackId => $internalTx) {
            if (! $gatewayMap->has($trackId)) {
                $status = $internalTx->status instanceof TransactionStatus
                    ? $internalTx->status->value
                    : (string) $internalTx->status;

                // Only flag captured transactions as missing in gateway
                if ($status === TransactionStatus::Captured->value) {
                    $missingInGateway[] = [
                        'track_id'       => $trackId,
                        'internal_amount'=> $internalTx->amount,
                        'internal_status'=> $status,
                    ];
                }
            }
        }

        // 4. Persist items
        $this->persistItems($report, $missingInInternal, $amountMismatches, $statusMismatches, $missingInGateway);

        return new ReconciliationResultDTO(
            gateway:             $gateway,
            periodFrom:          $from,
            periodTo:            $to,
            totalGateway:        $gatewayMap->count(),
            totalInternal:       $internalMap->count(),
            matched:             $matched,
            missingInInternal:   count($missingInInternal),
            missingInGateway:    count($missingInGateway),
            amountMismatch:      count($amountMismatches),
            statusMismatch:      count($statusMismatches),
            totalGatewayAmount:  (float) collect($gatewayTxns)->sum(fn($t) => $this->resolveGatewayAmount($gateway, $t)),
            totalInternalAmount: (float) $internal->sum('amount'),
            missingItems:        $missingInInternal,
            mismatchItems:       array_merge($amountMismatches, $statusMismatches),
        );
    }

    public function detectMissing(string $gateway, string $from, string $to): array
    {
        $gatewayTxns = $this->fetchGatewayTransactions($gateway, $from, $to);
        $internal    = $this->txRepo->forReconciliation($gateway, $from, $to)->keyBy('track_id');
        $missing     = [];

        foreach ($gatewayTxns as $gtx) {
            $trackId = $this->resolveGatewayTrackId($gateway, $gtx);
            if (! $internal->has($trackId)) {
                $missing[] = $gtx;
            }
        }

        return $missing;
    }

    public function detectMismatches(string $gateway, string $from, string $to): array
    {
        $result = $this->reconcile($gateway, $from, $to, new ReconciliationReport());
        return $result->mismatchItems;
    }

    public function generateReport(ReconciliationResultDTO $result): array
    {
        return $result->toArray();
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function fetchGatewayTransactions(string $gateway, string $from, string $to): array
    {
        try {
            return $this->paymentManager->driver($gateway)->fetchTransactions($from, $to);
        } catch (\Throwable) {
            return [];
        }
    }

    private function resolveGatewayTrackId(string $gateway, mixed $tx): string
    {
        return match ($gateway) {
            'myfatoorah' => (string) ($tx['CustomerReference'] ?? ''),
            'tap'        => (string) ($tx['reference']['transaction'] ?? ''),
            'stripe'     => (string) ($tx['client_reference_id'] ?? ''),
            default      => (string) ($tx['trackid'] ?? $tx['track_id'] ?? ''),
        };
    }

    private function resolveGatewayTxId(string $gateway, mixed $tx): string
    {
        return match ($gateway) {
            'myfatoorah' => (string) ($tx['TransactionId'] ?? ''),
            'tap'        => (string) ($tx['id'] ?? ''),
            'stripe'     => (string) ($tx['id'] ?? ''),
            default      => (string) ($tx['paymentid'] ?? ''),
        };
    }

    private function resolveGatewayAmount(string $gateway, mixed $tx): float
    {
        return (float) match ($gateway) {
            'myfatoorah' => $tx['TransactionValue'] ?? 0,
            'tap'        => $tx['amount'] ?? 0,
            'stripe'     => ($tx['amount'] ?? 0) / 100,
            default      => $tx['amt'] ?? $tx['amount'] ?? 0,
        };
    }

    private function resolveGatewayStatus(string $gateway, mixed $tx): string
    {
        return (string) match ($gateway) {
            'myfatoorah' => $tx['TransactionStatus'] ?? '',
            'tap'        => $tx['status'] ?? '',
            'stripe'     => $tx['status'] ?? '',
            default      => $tx['result'] ?? '',
        };
    }

    private function persistItems(
        ReconciliationReport $report,
        array $missingInInternal,
        array $amountMismatches,
        array $statusMismatches,
        array $missingInGateway,
    ): void {
        foreach ($missingInInternal as $item) {
            ReconciliationItem::create(array_merge($item, [
                'report_id'  => $report->id,
                'issue_type' => 'missing_in_internal',
            ]));
        }
        foreach ($missingInGateway as $item) {
            ReconciliationItem::create(array_merge($item, [
                'report_id'  => $report->id,
                'issue_type' => 'missing_in_gateway',
            ]));
        }
        foreach ($amountMismatches as $item) {
            ReconciliationItem::create(array_merge($item, [
                'report_id'  => $report->id,
                'issue_type' => 'amount_mismatch',
            ]));
        }
        foreach ($statusMismatches as $item) {
            ReconciliationItem::create(array_merge($item, [
                'report_id'  => $report->id,
                'issue_type' => 'status_mismatch',
            ]));
        }
    }

    private function updateReport(ReconciliationReport $report, ReconciliationResultDTO $result): void
    {
        $report->update([
            'total_gateway_transactions' => $result->totalGateway,
            'total_internal_transactions'=> $result->totalInternal,
            'matched_count'              => $result->matched,
            'missing_in_internal'        => $result->missingInInternal,
            'missing_in_gateway'         => $result->missingInGateway,
            'amount_mismatch_count'      => $result->amountMismatch,
            'status_mismatch_count'      => $result->statusMismatch,
            'total_gateway_amount'       => $result->totalGatewayAmount,
            'total_internal_amount'      => $result->totalInternalAmount,
            'report_data'                => $result->toArray(),
        ]);
    }
}

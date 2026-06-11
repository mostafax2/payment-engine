<?php

declare(strict_types=1);

namespace Mostafax\PaymentEngine\Console\Commands;

use Illuminate\Console\Command;
use Mostafax\PaymentEngine\Jobs\ReconcileTransactionsJob;
use Mostafax\PaymentEngine\Services\ReconciliationEngine;

final class ReconcileCommand extends Command
{
    protected $signature = 'payment:reconcile
                            {gateway? : Gateway name}
                            {--from=  : Start date Y-m-d (default: yesterday)}
                            {--to=    : End date Y-m-d (default: yesterday)}
                            {--async  : Dispatch as a background job}
                            {--tenant=: Tenant ID}';

    protected $description = 'Run payment reconciliation between gateway and local database';

    public function handle(ReconciliationEngine $engine): int
    {
        $gateway  = $this->argument('gateway') ?? config('payment-engine.default', 'knet');
        $from     = $this->option('from') ?? now()->subDay()->toDateString();
        $to       = $this->option('to')   ?? now()->subDay()->toDateString();
        $tenantId = $this->option('tenant');

        if ($this->option('async')) {
            ReconcileTransactionsJob::dispatch($gateway, $from, $to, $tenantId)
                ->onQueue(config('payment-engine.queues.reconcile', 'payment-reconcile'));

            $this->info("Reconciliation job dispatched for [{$gateway}] {$from} → {$to}");
            return self::SUCCESS;
        }

        $this->info("Reconciling [{$gateway}] {$from} → {$to}...");

        $result = $engine->run($gateway, $from, $to, $tenantId);

        $this->table(['Metric', 'Value'], [
            ['Gateway',              $result->gateway],
            ['Period',               "{$result->periodFrom} → {$result->periodTo}"],
            ['Gateway Transactions', $result->totalGateway],
            ['Internal Transactions',$result->totalInternal],
            ['Matched',              $result->matched],
            ['Missing in Internal',  $result->missingInInternal],
            ['Missing in Gateway',   $result->missingInGateway],
            ['Amount Mismatches',    $result->amountMismatch],
            ['Status Mismatches',    $result->statusMismatch],
            ['Gateway Total',        number_format($result->totalGatewayAmount, 3) . ' ' . 'KWD'],
            ['Internal Total',       number_format($result->totalInternalAmount, 3) . ' ' . 'KWD'],
        ]);

        if ($result->missingInInternal > 0 || $result->amountMismatch > 0) {
            $this->warn('⚠  Reconciliation found discrepancies. Review pe_reconciliation_items for details.');
        } else {
            $this->info('✓ Reconciliation clean — no discrepancies.');
        }

        return self::SUCCESS;
    }
}

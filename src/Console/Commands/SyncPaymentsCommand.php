<?php

declare(strict_types=1);

namespace Mostafax\PaymentEngine\Console\Commands;

use Illuminate\Console\Command;
use Mostafax\PaymentEngine\Services\BackfillEngine;

final class SyncPaymentsCommand extends Command
{
    protected $signature = 'payment:sync
                            {gateway? : Gateway name (knet, myfatoorah, tap, stripe...)}
                            {--from= : Start date Y-m-d}
                            {--to=   : End date Y-m-d}
                            {--missing : Only sync missing transactions}
                            {--tenant= : Tenant ID (multi-tenant)}';

    protected $description = 'Sync payment transactions from gateway to local database';

    public function handle(BackfillEngine $engine): int
    {
        $gateway  = $this->argument('gateway') ?? config('payment-engine.default', 'knet');
        $from     = $this->option('from') ?? now()->startOfDay()->toDateString();
        $to       = $this->option('to')   ?? now()->endOfDay()->toDateString();
        $tenantId = $this->option('tenant');
        $missing  = (bool) $this->option('missing');

        $this->info("Syncing [{$gateway}] transactions from {$from} to {$to}...");

        $stats = $missing
            ? $engine->syncMissing($gateway, $from, $to, $tenantId)
            : $engine->sync($gateway, $from, $to, $tenantId);

        $this->table(
            ['Metric', 'Count'],
            collect($stats)->map(fn($v, $k) => [ucwords(str_replace('_', ' ', $k)), $v])->values()->toArray(),
        );

        $this->info('Done.');
        return self::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace Mostafax\PaymentEngine\Console\Commands;

use Illuminate\Console\Command;
use Mostafax\PaymentEngine\Services\RecoveryEngine;

final class RecoverMissingCommand extends Command
{
    protected $signature = 'payment:recover
                            {--minutes=30 : Mark transactions older than N minutes as stale}
                            {--max=1000   : Maximum transactions to process per run}';

    protected $description = 'Attempt to recover stale/pending payment transactions by querying gateways';

    public function handle(RecoveryEngine $engine): int
    {
        $minutes = (int) $this->option('minutes');
        $max     = (int) $this->option('max');

        $this->info("Scanning for pending transactions older than {$minutes} minutes (max {$max})...");

        $stats = $engine->recoverStale($minutes, $max);

        $this->table(['Metric', 'Count'], [
            ['Total Scanned',    $stats['total']],
            ['Recovered',        $stats['recovered']],
            ['Still Pending',    $stats['still_pending']],
            ['Errors',           $stats['failed']],
        ]);

        if ($stats['recovered'] > 0) {
            $this->info("✓ Recovered {$stats['recovered']} transaction(s).");
        }

        return self::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace Mostafax\PaymentEngine\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Mostafax\PaymentEngine\Services\RecoveryEngine;

final class RecoverStaleTransactionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries   = 1;
    public int $timeout = 300;

    public function __construct(
        public readonly int $olderThanMinutes = 30,
        public readonly int $maxPerRun        = 1000,
    ) {}

    public function handle(RecoveryEngine $engine): void
    {
        $stats = $engine->recoverStale($this->olderThanMinutes, $this->maxPerRun);
        Log::info('PaymentEngine recovery job completed', $stats);
    }
}

<?php

declare(strict_types=1);

namespace Mostafax\PaymentEngine\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Mostafax\PaymentEngine\Services\ReconciliationEngine;

final class ReconcileTransactionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries   = 1;
    public int $timeout = 300;

    public function __construct(
        public readonly string  $gateway,
        public readonly string  $from,
        public readonly string  $to,
        public readonly ?string $tenantId = null,
    ) {}

    public function handle(ReconciliationEngine $engine): void
    {
        $engine->run(
            gateway:  $this->gateway,
            from:     $this->from,
            to:       $this->to,
            tenantId: $this->tenantId,
        );
    }
}

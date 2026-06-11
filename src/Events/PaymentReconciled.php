<?php

declare(strict_types=1);

namespace Mostafax\PaymentEngine\Events;

use Mostafax\PaymentEngine\DTOs\ReconciliationResultDTO;
use Mostafax\PaymentEngine\Models\ReconciliationReport;

final class PaymentReconciled
{
    public function __construct(
        public readonly ReconciliationReport    $report,
        public readonly ReconciliationResultDTO $result,
    ) {}
}

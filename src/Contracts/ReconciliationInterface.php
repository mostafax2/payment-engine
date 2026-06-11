<?php

declare(strict_types=1);

namespace Mostafax\PaymentEngine\Contracts;

use Mostafax\PaymentEngine\DTOs\ReconciliationResultDTO;

interface ReconciliationInterface
{
    public function run(string $gateway, string $from, string $to, ?string $tenantId = null): ReconciliationResultDTO;

    public function detectMissing(string $gateway, string $from, string $to): array;

    public function detectMismatches(string $gateway, string $from, string $to): array;

    public function generateReport(ReconciliationResultDTO $result): array;
}

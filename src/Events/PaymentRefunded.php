<?php

declare(strict_types=1);

namespace Mostafax\PaymentEngine\Events;

use Mostafax\PaymentEngine\Models\Transaction;

final class PaymentRefunded
{
    public function __construct(
        public readonly Transaction $transaction,
        public readonly float       $amount,
        public readonly string      $reason = '',
    ) {}
}

<?php

declare(strict_types=1);

namespace Mostafax\PaymentEngine\Events;

use Mostafax\PaymentEngine\DTOs\InitiatePaymentDTO;
use Mostafax\PaymentEngine\Models\Transaction;

final class PaymentInitiated
{
    public function __construct(
        public readonly Transaction       $transaction,
        public readonly InitiatePaymentDTO $dto,
    ) {}
}

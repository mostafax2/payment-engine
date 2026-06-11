<?php

declare(strict_types=1);

namespace Mostafax\PaymentEngine\Events;

use Mostafax\PaymentEngine\DTOs\GatewayResponseDTO;
use Mostafax\PaymentEngine\Models\Transaction;

final class PaymentRecovered
{
    public function __construct(
        public readonly Transaction      $transaction,
        public readonly GatewayResponseDTO $response,
    ) {}
}

<?php

declare(strict_types=1);

namespace Mostafax\PaymentEngine\Facades;

use Illuminate\Support\Facades\Facade;
use Mostafax\PaymentEngine\DTOs\GatewayResponseDTO;
use Mostafax\PaymentEngine\DTOs\InitiatePaymentDTO;
use Mostafax\PaymentEngine\Models\Transaction;
use Mostafax\PaymentEngine\Services\PaymentManager;

/**
 * @method static array             initiate(InitiatePaymentDTO $dto)
 * @method static Transaction       handleCallback(string $gateway, array $payload)
 * @method static GatewayResponseDTO inquire(string $trackId, string $gateway, ?float $amount = null)
 */
final class PaymentEngine extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PaymentManager::class;
    }
}

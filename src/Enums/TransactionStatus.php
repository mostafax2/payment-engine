<?php

declare(strict_types=1);

namespace Mostafax\PaymentEngine\Enums;

enum TransactionStatus: string
{
    case Initiated  = 'initiated';
    case Pending    = 'pending';
    case Captured   = 'captured';
    case Failed     = 'failed';
    case Cancelled  = 'cancelled';
    case Refunded   = 'refunded';
    case Reversed   = 'reversed';
    case Unknown    = 'unknown';

    public function label(): string
    {
        return match($this) {
            self::Initiated => 'Initiated',
            self::Pending   => 'Pending',
            self::Captured  => 'Captured',
            self::Failed    => 'Failed',
            self::Cancelled => 'Cancelled',
            self::Refunded  => 'Refunded',
            self::Reversed  => 'Reversed',
            self::Unknown   => 'Unknown',
        };
    }

    public function isTerminal(): bool
    {
        return match($this) {
            self::Captured, self::Failed, self::Cancelled,
            self::Refunded, self::Reversed => true,
            default => false,
        };
    }

    public function isSuccessful(): bool
    {
        return $this === self::Captured;
    }
}

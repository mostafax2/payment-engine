<?php

declare(strict_types=1);

namespace Mostafax\PaymentEngine\Enums;

enum GatewayType: string
{
    case Knet       = 'knet';
    case MyFatoorah = 'myfatoorah';
    case Tap        = 'tap';
    case PayTabs    = 'paytabs';
    case Stripe     = 'stripe';
    case PayPal     = 'paypal';

    public function label(): string
    {
        return match($this) {
            self::Knet       => 'KNET (Kuwait)',
            self::MyFatoorah => 'MyFatoorah',
            self::Tap        => 'Tap Payments',
            self::PayTabs    => 'PayTabs',
            self::Stripe     => 'Stripe',
            self::PayPal     => 'PayPal',
        };
    }
}

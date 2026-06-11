<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Gateway
    |--------------------------------------------------------------------------
    */
    'default' => env('PAYMENT_GATEWAY', 'knet'),

    /*
    |--------------------------------------------------------------------------
    | Table Names
    |--------------------------------------------------------------------------
    */
    'tables' => [
        'transactions'          => 'pe_transactions',
        'transaction_events'    => 'pe_transaction_events',
        'webhook_payloads'      => 'pe_webhook_payloads',
        'webhook_attempts'      => 'pe_webhook_attempts',
        'reconciliation_reports'=> 'pe_reconciliation_reports',
        'reconciliation_items'  => 'pe_reconciliation_items',
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-Tenant Support
    |--------------------------------------------------------------------------
    */
    'multi_tenant'   => false,
    'tenant_column'  => 'tenant_id',
    'tenant_resolver'=> null, // callable: fn() => auth()->user()?->tenant_id

    /*
    |--------------------------------------------------------------------------
    | Payment Gateways
    |--------------------------------------------------------------------------
    */
    'gateways' => [

        'knet' => [
            'driver'       => \Mostafax\PaymentEngine\Drivers\KnetDriver::class,
            'label'        => 'KNET (Kuwait)',
            'currency'     => env('KNET_CURRENCY', 'KWD'),
            'transport_id' => env('KNET_TRANSPORT_ID'),
            'password'     => env('KNET_TRANSPORT_PASSWORD'),
            'resource_key' => env('KNET_RESOURCE_KEY'),
            'action'       => env('KNET_ACTION_CODE', '1'),
            'language'     => env('KNET_LANGUAGE', 'ENG'),
            'test_url'     => env('KNET_TEST_URL', 'https://kpay.com.kw/kpg/PaymentHTTP.htm?param=paymentInit'),
            'live_url'     => env('KNET_LIVE_URL', 'https://kpay.com.kw/kpg/PaymentHTTP.htm?param=paymentInit'),
            'inquiry_url'  => env('KNET_INQUIRY_URL', 'https://kpay.com.kw/kpg/tranPipe.htm?param=tranInit'),
            'success_url'  => env('KNET_SUCCESS_URL'),
            'error_url'    => env('KNET_ERROR_URL'),
            'sandbox'      => env('KNET_SANDBOX', true),
        ],

        'myfatoorah' => [
            'driver'    => \Mostafax\PaymentEngine\Drivers\MyFatoorahDriver::class,
            'label'     => 'MyFatoorah',
            'api_key'   => env('MYFATOORAH_API_KEY'),
            'base_url'  => env('MYFATOORAH_BASE_URL', 'https://api.myfatoorah.com'),
            'sandbox'   => env('MYFATOORAH_SANDBOX', true),
            'currency'  => env('MYFATOORAH_CURRENCY', 'KWD'),
        ],

        'tap' => [
            'driver'     => \Mostafax\PaymentEngine\Drivers\TapDriver::class,
            'label'      => 'Tap Payments',
            'secret_key' => env('TAP_SECRET_KEY'),
            'public_key' => env('TAP_PUBLIC_KEY'),
            'base_url'   => 'https://api.tap.company/v2',
            'sandbox'    => env('TAP_SANDBOX', true),
            'currency'   => env('TAP_CURRENCY', 'KWD'),
        ],

        'paytabs' => [
            'driver'       => \Mostafax\PaymentEngine\Drivers\PayTabsDriver::class,
            'label'        => 'PayTabs',
            'profile_id'   => env('PAYTABS_PROFILE_ID'),
            'server_key'   => env('PAYTABS_SERVER_KEY'),
            'base_url'     => env('PAYTABS_BASE_URL', 'https://secure.paytabs.com'),
            'sandbox'      => env('PAYTABS_SANDBOX', true),
            'currency'     => env('PAYTABS_CURRENCY', 'KWD'),
        ],

        'stripe' => [
            'driver'      => \Mostafax\PaymentEngine\Drivers\StripeDriver::class,
            'label'       => 'Stripe',
            'secret_key'  => env('STRIPE_SECRET_KEY'),
            'public_key'  => env('STRIPE_PUBLIC_KEY'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
            'base_url'    => 'https://api.stripe.com/v1',
            'sandbox'     => env('STRIPE_SANDBOX', true),
            'currency'    => env('STRIPE_CURRENCY', 'usd'),
        ],

        'paypal' => [
            'driver'        => \Mostafax\PaymentEngine\Drivers\PayPalDriver::class,
            'label'         => 'PayPal',
            'client_id'     => env('PAYPAL_CLIENT_ID'),
            'client_secret' => env('PAYPAL_CLIENT_SECRET'),
            'base_url'      => env('PAYPAL_SANDBOX', true)
                ? 'https://api-m.sandbox.paypal.com'
                : 'https://api-m.paypal.com',
            'sandbox'       => env('PAYPAL_SANDBOX', true),
            'currency'      => env('PAYPAL_CURRENCY', 'USD'),
        ],

        'fawry' => [
            'driver'           => \Mostafax\PaymentEngine\Drivers\FawryDriver::class,
            'label'            => 'Fawry (Egypt)',
            'merchant_code'    => env('FAWRY_MERCHANT_CODE'),
            'secure_key'       => env('FAWRY_SECURE_KEY'),
            'return_url'       => env('FAWRY_RETURN_URL'),
            'verify_signature' => env('FAWRY_VERIFY_SIGNATURE', true),
            'sandbox'          => env('FAWRY_SANDBOX', true),
            'currency'         => 'EGP',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Reliability
    |--------------------------------------------------------------------------
    */
    'webhook' => [
        'queue'           => env('PAYMENT_WEBHOOK_QUEUE', 'payment-webhooks'),
        'max_attempts'    => 5,
        'retry_delays'    => [60, 300, 900, 3600, 86400], // seconds: 1m 5m 15m 1h 24h
        'idempotency_ttl' => 86400, // 24 hours in seconds
        'signature_header'=> 'X-Payment-Signature',
    ],

    /*
    |--------------------------------------------------------------------------
    | Reconciliation Engine
    |--------------------------------------------------------------------------
    */
    'reconciliation' => [
        'queue'              => env('PAYMENT_RECONCILE_QUEUE', 'payment-reconcile'),
        'batch_size'         => 500,
        'amount_tolerance'   => 0.01, // KD — floating point comparison tolerance
        'auto_recover'       => true,
        'schedule'           => '0 2 * * *', // daily at 2 AM
        'retention_days'     => 90,
    ],

    /*
    |--------------------------------------------------------------------------
    | Recovery Engine
    |--------------------------------------------------------------------------
    */
    'recovery' => [
        'queue'           => env('PAYMENT_RECOVERY_QUEUE', 'payment-recovery'),
        'lookback_hours'  => 48,
        'max_per_run'     => 1000,
        'notify_on_recovery' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Transaction Ledger
    |--------------------------------------------------------------------------
    */
    'ledger' => [
        'immutable'       => true,  // disallow direct status updates; use events
        'archive_after_days' => 365,
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring & Notifications
    |--------------------------------------------------------------------------
    */
    'monitoring' => [
        'channels'  => ['database', 'mail'],
        'slack_webhook' => env('PAYMENT_SLACK_WEBHOOK'),
        'telegram_bot_token' => env('PAYMENT_TELEGRAM_BOT_TOKEN'),
        'telegram_chat_id'   => env('PAYMENT_TELEGRAM_CHAT_ID'),
        'mail_to'   => env('PAYMENT_NOTIFY_EMAIL'),
        'alerts'    => [
            'failed_threshold'    => 10,
            'recovery_threshold'  => 5,
            'webhook_dlq_threshold' => 3,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Names
    |--------------------------------------------------------------------------
    */
    'queues' => [
        'payments'      => env('PAYMENT_QUEUE', 'payments'),
        'webhooks'      => env('PAYMENT_WEBHOOK_QUEUE', 'payment-webhooks'),
        'reconcile'     => env('PAYMENT_RECONCILE_QUEUE', 'payment-reconcile'),
        'recovery'      => env('PAYMENT_RECOVERY_QUEUE', 'payment-recovery'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Prefix
    |--------------------------------------------------------------------------
    */
    'route_prefix'   => env('PAYMENT_ROUTE_PREFIX', 'payment'),
    'api_prefix'     => env('PAYMENT_API_PREFIX', 'api/payment'),
    'api_middleware' => ['api', 'auth:sanctum'],

];

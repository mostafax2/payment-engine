# mostafax/payment-engine

> **Enterprise Payment Orchestration, Reconciliation, Recovery & Monitoring Platform for Laravel**

[![Latest Version](https://img.shields.io/badge/version-2.0.0-brightgreen.svg)](https://github.com/mostafax2/payment-engine/releases)
[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-blue.svg)](https://www.php.net)
[![Laravel Version](https://img.shields.io/badge/Laravel-10%2B-red.svg)](https://laravel.com)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](https://choosealicense.com/licenses/mit/)

---

## Overview

`mostafax/payment-engine` transforms any Laravel application into a full payment orchestration platform. It provides a unified driver interface across **6 payment gateways**, an immutable transaction ledger, a webhook reliability layer with Dead Letter Queue, automated reconciliation, stale-transaction recovery, and historical backfill — all event-driven and multi-tenant ready.

**Author:** [Mostafa Elbayyar](https://github.com/mostafax2)  
**Documentation:** [GitHub Pages](https://mostafax2.github.io/payment-engine/)  
**Support:** mostafa.m.elbiar2@gmail.com

---

## Supported Gateways

| Gateway      | Region       | Status          |
|--------------|--------------|-----------------|
| KNET         | Kuwait/Gulf  | ✅ Full Support  |
| MyFatoorah   | Gulf & MENA  | ✅ Full Support  |
| Tap Payments | MENA         | ✅ Full Support  |
| PayTabs      | MENA/Global  | ✅ Full Support  |
| Stripe       | Global       | ✅ Full Support  |
| PayPal       | Global       | ✅ Full Support  |

---

## Key Features

- **Multi-Gateway Driver Architecture** — Strategy Pattern. Add new gateways by implementing `PaymentDriverInterface`.
- **Immutable Transaction Ledger** — ULID-keyed `pe_transactions` + append-only `pe_transaction_events` audit trail.
- **Webhook Reliability Layer** — Raw payload persistence, SHA-256 idempotency, exponential backoff (1m→5m→15m→1h→24h), Dead Letter Queue.
- **Reconciliation Engine** — Automated comparison of gateway vs internal records with mismatch detection (amount tolerance: ±0.01).
- **Auto-Recovery Engine** — Scans stale pending transactions and self-heals via gateway inquiry.
- **Historical Backfill Engine** — Full or gap-only sync from gateway for any date range.
- **6 Domain Events** — Decoupled lifecycle: `PaymentInitiated`, `PaymentCaptured`, `PaymentFailed`, `PaymentRefunded`, `PaymentRecovered`, `PaymentReconciled`.
- **Multi-Tenant Support** — `tenant_id` scoping via configurable resolver callable.
- **3 Artisan Commands** — `payment:sync`, `payment:reconcile`, `payment:recover`.
- **REST API** — 10 Sanctum-protected endpoints.

---

## Requirements

- PHP 8.2+
- Laravel 10, 11, or 13
- MySQL 8+ or MariaDB 10.3+
- Redis (recommended for queue workers)

---

## Installation

### Option A — One Command (Recommended)

```bash
composer require mostafax/payment-engine
php artisan payment:install
```

`payment:install` automatically:
- Publishes `config/payment-engine.php`
- Runs the 6 database migrations
- Patches CSRF exclusions in `VerifyCsrfToken.php` (Laravel 10) or `bootstrap/app.php` (Laravel 11+)
- Appends `.env` stubs for gateway credentials

### Option B — Manual

```bash
composer require mostafax/payment-engine
php artisan vendor:publish --tag=payment-engine-config
php artisan migrate
```

### Configure `.env`

```env
# Default gateway
PAYMENT_GATEWAY=knet

# KNET credentials
KNET_TRANSPORT_ID=your_transport_id
KNET_TRANSPORT_PASSWORD=your_password
KNET_RESOURCE_KEY=your_resource_key
KNET_SUCCESS_URL=https://yourapp.com/payment/knet/success
KNET_ERROR_URL=https://yourapp.com/payment/knet/error
KNET_SANDBOX=true

# Stripe (optional)
STRIPE_SECRET_KEY=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...

# PayPal (optional)
PAYPAL_CLIENT_ID=...
PAYPAL_CLIENT_SECRET=...

# Queue names
PAYMENT_QUEUE=payments
PAYMENT_WEBHOOK_QUEUE=payment-webhooks
PAYMENT_RECONCILE_QUEUE=payment-reconcile
PAYMENT_RECOVERY_QUEUE=payment-recovery
```

### 4. Exclude Callbacks from CSRF

```php
// app/Http/Middleware/VerifyCsrfToken.php
protected $except = [
    'payment/*/success',
    'payment/*/error',
    'api/payment/webhook/*',
];
```

### 5. Start Queue Workers

```bash
php artisan queue:work --queue=payment-webhooks
php artisan queue:work --queue=payment-reconcile
php artisan queue:work --queue=payment-recovery
```

---

## Basic Usage

### Initiate a Payment

```php
use Mostafax\PaymentEngine\Facades\PaymentEngine;
use Mostafax\PaymentEngine\DTOs\InitiatePaymentDTO;
use Illuminate\Support\Str;

$dto = InitiatePaymentDTO::fromArray([
    'gateway'     => 'knet',
    'track_id'    => (string) Str::ulid(),
    'amount'      => 25.500,
    'currency'    => 'KWD',
    'success_url' => route('payment.return'),
    'error_url'   => route('payment.cancel'),
    'metadata'    => ['order_id' => $order->id],
]);

$result = PaymentEngine::initiate($dto);

return redirect($result['redirect_url']);
```

### Listen to Events

```php
// In EventServiceProvider::boot()
use Mostafax\PaymentEngine\Events\PaymentCaptured;

Event::listen(PaymentCaptured::class, function (PaymentCaptured $event) {
    Order::find($event->transaction->metadata['order_id'])?->markAsPaid();
});
```

### Live Gateway Inquiry

```php
$response = PaymentEngine::inquire(
    trackId: $trackId,
    gateway: 'knet',
    amount:  25.500,
);
// $response → GatewayResponseDTO
```

---

## Reconciliation

```bash
# Sync date range
php artisan payment:reconcile knet --from=2026-06-01 --to=2026-06-10

# Async (background job)
php artisan payment:reconcile knet --async

# Recover stale pending transactions (>30 min)
php artisan payment:recover --minutes=30 --max=1000
```

---

## Historical Backfill

```bash
# Full sync for a date range
php artisan payment:sync knet --from=2026-01-01 --to=2026-01-31

# Gap-fill only (safe to re-run, skips existing)
php artisan payment:sync knet --missing
```

---

## API Endpoints

All endpoints require `Authorization: Bearer {token}` (Laravel Sanctum) except webhook receivers.

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/api/payment/initiate` | Initiate payment, get redirect URL |
| `GET`  | `/api/payment/transactions` | Paginated list (filter by gateway/status/date) |
| `GET`  | `/api/payment/transactions/{ulid}` | Transaction + full audit trail |
| `POST` | `/api/payment/inquire/{trackId}` | Live gateway status check |
| `POST` | `/api/payment/reconcile` | Trigger reconciliation |
| `GET`  | `/api/payment/reconciliation/reports` | Paginated reports |
| `GET`  | `/api/payment/reconciliation/reports/{ulid}` | Report with mismatch items |
| `POST` | `/api/payment/webhook/{gateway}` | Receive push webhooks (no auth, sig verified) |
| `ANY`  | `/payment/{gateway}/success` | KNET/PayTabs success callback |
| `ANY`  | `/payment/{gateway}/error` | KNET/PayTabs error callback |

---

## Database Tables

| Table | Purpose |
|-------|---------|
| `pe_transactions` | Central payment ledger (ULID, multi-tenant, status lifecycle) |
| `pe_transaction_events` | Append-only audit trail for every status change |
| `pe_webhook_payloads` | Raw payload persistence + retry state |
| `pe_webhook_attempts` | Per-attempt log with exception trace |
| `pe_reconciliation_reports` | Reconciliation run summaries |
| `pe_reconciliation_items` | Individual mismatch records per report |

Table names are configurable via `config('payment-engine.tables.*')`.

---

## Custom Gateway Driver

Implement `PaymentDriverInterface` to add any gateway:

```php
use Mostafax\PaymentEngine\Contracts\PaymentDriverInterface;
use Mostafax\PaymentEngine\DTOs\InitiatePaymentDTO;
use Mostafax\PaymentEngine\DTOs\GatewayResponseDTO;

final class MyGatewayDriver implements PaymentDriverInterface
{
    public function initiate(InitiatePaymentDTO $dto): array { ... }
    public function handleCallback(array $payload): GatewayResponseDTO { ... }
    public function inquire(string $trackId, ?float $amount = null): GatewayResponseDTO { ... }
    public function fetchTransactions(string $from, string $to, int $page = 1, int $perPage = 500): array { ... }
    public function getGatewayName(): string { return 'my-gateway'; }
    public function getSupportedCurrencies(): array { return ['KWD', 'USD']; }
}
```

Register it in your `ServiceProvider`:

```php
config(['payment-engine.gateways.my-gateway' => [
    'driver' => MyGatewayDriver::class,
    'api_key' => env('MY_GATEWAY_KEY'),
    'sandbox' => env('MY_GATEWAY_SANDBOX', true),
]]);
```

---

## Upgrade from 1.x (KNET-only)

1. Update `composer.json`: `"mostafax/payment-engine": "^2.0"`
2. Publish and run new migrations: `php artisan vendor:publish --tag=payment-engine-migrations && php artisan migrate`
3. Publish new config: `php artisan vendor:publish --tag=payment-engine-config`
4. Replace `.env` keys with new `KNET_*` prefixed names (see [Installation](#installation) above)
5. Replace old CSRF exclusion `knet/*` with `payment/*/success`, `payment/*/error`
6. Update namespace: `Mostafax\Knet\*` → `Mostafax\PaymentEngine\*`

---

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for full release history.

---

## License

MIT — see [LICENSE](LICENSE) for details.

---

## Author

**Mostafa Elbayyar**  
Senior Software Engineer & Laravel Package Author  
GitHub: [@mostafax2](https://github.com/mostafax2)  
Email: mostafa.m.elbiar2@gmail.com

# Changelog — mostafax/payment-engine

All notable changes adhere to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [2.1.0] — 2026-06-11

### Added

#### Fawry Payment Gateway (Egypt)
- **FawryDriver** — full integration with Fawry hosted payment API v2
  - `initiate()`: SHA-256 charge signature, hosted redirect + Fawry outlet reference
  - `handleCallback()`: MD5 callback signature verification, maps `PAID` → `Captured`
  - `inquire()`: `GET /status/v2` with SHA-256 signature, live status check
  - `fetchTransactions()`: returns `[]` (Fawry has no batch-fetch endpoint)
  - Supports both card payment (redirect) and cash at Fawry outlets
- `GatewayType::Fawry` enum case
- `config/payment-engine.php`: `fawry` gateway block with `merchant_code`, `secure_key`, `return_url`, `verify_signature`
- `InstallCommand`: Fawry `.env` stubs appended on `payment:install`

#### Developer Experience
- `payment:install` command for zero-config setup (publish config, migrate, patch CSRF, append `.env`)
- GitHub Pages documentation with bilingual EN/AR toggle

### Changed
- `composer.json` version bumped to `2.1.0`
- `composer.json` description and keywords updated to include Fawry/Egypt
- Documentation updated: 7 gateways stat, Fawry card added to gateway grid

---

## [2.0.0] — 2026-06-11 🚀 Complete Architecture Rebuild

> **Breaking Change**: Package renamed from `mostafax/knet` to `mostafax/payment-engine`.
> Namespace changed from `Mostafax\Knet` to `Mostafax\PaymentEngine`.
> All database tables now use the `pe_` prefix (new migrations required).

### Added

#### Multi-Gateway Driver Architecture
- **AbstractPaymentDriver** — base class with Guzzle HTTP, retry, sandbox toggle
- **KnetDriver** — full Kuwait KNET integration (AES-128-CBC, inquiry API)
- **MyFatoorahDriver** — session-based initiation + status inquiry
- **TapDriver** — charges API, redirect flow
- **PayTabsDriver** — sale transaction, query API
- **StripeDriver** — Checkout Sessions API, webhook signature verification
- **PayPalDriver** — Orders v2 API, OAuth2 token management
- `PaymentDriverInterface` — Strategy Pattern contract; new gateways require only this interface

#### Transaction Ledger (Immutable Audit Trail)
- `pe_transactions` — central ledger with ULID, tenant_id, status lifecycle timestamps
- `pe_transaction_events` — append-only audit trail for every state change
- `TransactionRepository` implementing `TransactionRepositoryInterface`
- `TransactionStatus` enum: initiated → pending → captured / failed / cancelled / refunded / reversed / unknown

#### Webhook Reliability Layer
- `pe_webhook_payloads` — raw HTTP body/headers persisted before processing
- `pe_webhook_attempts` — per-attempt success/failure with exception trace
- Idempotency key derivation per gateway (prevents duplicate processing)
- Exponential backoff retry: 1m → 5m → 15m → 1h → 24h
- Dead Letter Queue at `max_attempts` (default: 5)
- Stripe HMAC-SHA256 signature verification built-in
- `ProcessWebhookJob` dispatched on dedicated `payment-webhooks` queue

#### Reconciliation Engine
- `ReconciliationEngine` implementing `ReconciliationInterface`
- Compares gateway transactions vs internal ledger per date range
- Detects: missing in internal, missing in gateway, amount mismatch (tolerance: 0.01), status mismatch
- `pe_reconciliation_reports` + `pe_reconciliation_items` tables
- `ReconcileTransactionsJob` for async background reconciliation
- `PaymentReconciled` event fired on completion

#### Auto-Recovery Engine
- `RecoveryEngine::recoverStale()` — scans pending transactions older than N minutes, queries gateway
- `RecoveryEngine::rebuildFromGateway()` — creates missing internal records from gateway data
- `RecoverStaleTransactionsJob` for background recovery runs
- `PaymentRecovered` event fired on successful recovery

#### Historical Backfill Engine
- `BackfillEngine::sync()` — full date-range sync from gateway
- `BackfillEngine::syncMissing()` — gap-fill only (skips existing records)
- Artisan commands:
  ```
  php artisan payment:sync knet
  php artisan payment:sync knet --from=2026-01-01 --to=2026-01-31
  php artisan payment:sync knet --missing
  php artisan payment:reconcile knet --from=2026-01-01 --to=2026-01-31
  php artisan payment:reconcile knet --async
  php artisan payment:recover --minutes=30 --max=1000
  ```

#### Event-Driven Architecture
- `PaymentInitiated` — fired on new payment creation
- `PaymentCaptured` — fired on successful callback/webhook
- `PaymentFailed` — fired on error callback/webhook
- `PaymentRefunded` — fired on refund completion
- `PaymentRecovered` — fired on auto-recovery
- `PaymentReconciled` — fired on reconciliation completion

#### REST API (Sanctum-protected)
- `POST   /api/payment/initiate` — initiate payment, returns redirect URL
- `GET    /api/payment/transactions` — paginated transaction list with filters
- `GET    /api/payment/transactions/{ulid}` — single transaction with events
- `POST   /api/payment/inquire/{trackId}` — live gateway status check
- `POST   /api/payment/reconcile` — trigger reconciliation
- `GET    /api/payment/reconciliation/reports` — paginated reports
- `GET    /api/payment/reconciliation/reports/{ulid}` — report with items
- `POST   /api/payment/webhook/{gateway}` — receive push webhooks (Stripe, PayPal, Tap)
- `ANY    /payment/{gateway}/success` — KNET / PayTabs success redirect
- `ANY    /payment/{gateway}/error` — KNET / PayTabs error redirect

#### Multi-Tenant Support
- `tenant_id` column on transactions and reconciliation reports
- `config('payment-engine.tenant_resolver')` callable for automatic tenant injection
- Scoped queries via `forTenant()` scope

#### DTOs (Immutable, readonly)
- `InitiatePaymentDTO` — replaces udf1-5 with typed `metadata[]`
- `GatewayResponseDTO` — normalized response across all gateways
- `ReconciliationResultDTO` — structured reconciliation output with `toArray()` / `fromArray()`

### Changed
- `composer.json`: package renamed `mostafax/payment-engine`, version `2.0.0`
- Namespace: all classes now under `Mostafax\PaymentEngine\`
- Config: `payment-engine.php` replaces scattered `env()` calls throughout code
- Table prefix: `pe_` (was `payments`)
- All PHP files: `declare(strict_types=1)`, PSR-12, `final` where applicable
- Routes: registered via `ServiceProvider::boot()` from dedicated route files (not inside `register()`)

### Removed
- `Knet2.php` — raw deprecated class
- `KnetController.php` — replaced by `PaymentController` + `WebhookController`
- `KnetServiceProvider.php` — replaced by `PaymentEngineServiceProvider`
- `Models/Payment.php` — replaced by `Models/Transaction.php`
- `Repositories/PaymentRepository.php` — replaced by `TransactionRepository`
- `Requests/KnetRequest.php` — validation moved into controllers
- `Enums/StatusEnum.php` — replaced by `Enums/TransactionStatus.php`
- `Services/knet.php` — replaced by `Drivers/KnetDriver.php`
- `vendor/` committed to git — removed, now in `.gitignore`

---

## [1.0.0] — Legacy KNET-only

- Basic KNET payment initiation
- AES-128-CBC encryption/decryption
- Success / error callback handling
- `payments` table

---

## Upgrade Guide: 1.x → 2.0

1. Update your `composer.json`:
   ```json
   "require": {
       "mostafax/payment-engine": "^2.0"
   }
   ```

2. Run migrations:
   ```bash
   php artisan vendor:publish --tag=payment-engine-migrations
   php artisan migrate
   ```

3. Publish config:
   ```bash
   php artisan vendor:publish --tag=payment-engine-config
   ```

4. Update `.env` — rename keys:
   ```env
   KNET_TRANSPORT_ID=...
   KNET_TRANSPORT_PASSWORD=...
   KNET_RESOURCE_KEY=...
   KNET_SUCCESS_URL=https://yourapp.com/payment/knet/success
   KNET_ERROR_URL=https://yourapp.com/payment/knet/error
   ```

5. Add to `app/Http/Middleware/VerifyCsrfToken.php`:
   ```php
   protected $except = [
       'payment/*/success',
       'payment/*/error',
       'api/payment/webhook/*',
   ];
   ```

6. Update event listeners — replace old class references:

   | Old | New |
   |-----|-----|
   | — | `PaymentInitiated::class` |
   | — | `PaymentCaptured::class` |
   | — | `PaymentFailed::class` |
   | — | `PaymentRecovered::class` |
   | — | `PaymentReconciled::class` |

7. Update service bindings in your app:
   ```php
   use Mostafax\PaymentEngine\Facades\PaymentEngine;

   $result = PaymentEngine::initiate(InitiatePaymentDTO::fromArray([
       'gateway'     => 'knet',
       'track_id'    => (string) Str::ulid(),
       'amount'      => 25.500,
       'currency'    => 'KWD',
       'success_url' => route('payment.return'),
       'error_url'   => route('payment.cancel'),
       'metadata'    => ['user_id' => auth()->id(), 'order_id' => $order->id],
   ]));

   return redirect($result['redirect_url']);
   ```

[2.0.0]: https://github.com/mostafax2/payment-engine/releases/tag/v2.0.0
[1.0.0]: https://github.com/mostafax2/payment-engine/releases/tag/v1.0.0

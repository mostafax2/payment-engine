<?php

declare(strict_types=1);

namespace Mostafax\PaymentEngine\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

final class InstallCommand extends Command
{
    protected $signature   = 'payment:install {--force : Overwrite existing published config}';
    protected $description = 'Install Payment Engine — publish config, run migrations, configure CSRF exclusions';

    private const CSRF_LABEL = 'CSRF exclusions';

    public function handle(): int
    {
        $this->components->info('Installing Payment Engine v' . $this->packageVersion() . '...');
        $this->newLine();

        $this->publishConfig();
        $this->runMigrations();
        $this->patchCsrfExclusions();
        $this->printEnvStub();
        $this->printNextSteps();

        return self::SUCCESS;
    }

    private function publishConfig(): void
    {
        $this->components->task('Publishing configuration', function () {
            $this->callSilently('vendor:publish', [
                '--tag'   => 'payment-engine-config',
                '--force' => (bool) $this->option('force'),
            ]);
        });
    }

    private function runMigrations(): void
    {
        $this->components->task('Running migrations', function () {
            $this->callSilently('migrate');
        });
    }

    private function patchCsrfExclusions(): void
    {
        // Laravel 11+ uses bootstrap/app.php — VerifyCsrfToken may not exist
        $legacyPath    = app_path('Http/Middleware/VerifyCsrfToken.php');
        $bootstrapPath = base_path('bootstrap/app.php');

        if (File::exists($legacyPath)) {
            $this->patchLegacyCsrf($legacyPath);
        } elseif (File::exists($bootstrapPath)) {
            $this->patchBootstrapCsrf($bootstrapPath);
        }
    }

    private function patchLegacyCsrf(string $path): void
    {
        $contents = File::get($path);

        if (str_contains($contents, 'payment/*/success')) {
            $this->components->twoColumnDetail(self::CSRF_LABEL, '<fg=yellow>already present — skipped</>');
            return;
        }

        $this->components->task('Adding CSRF exclusions (VerifyCsrfToken)', function () use ($path, $contents) {
            $exclusions = "        'payment/*/success',\n        'payment/*/error',\n        'api/payment/webhook/*',\n";

            $patched = preg_replace(
                '/protected\s+\$except\s*=\s*\[/',
                "protected \$except = [\n{$exclusions}",
                $contents,
            );

            File::put($path, $patched ?? $contents);
        });
    }

    private function patchBootstrapCsrf(string $path): void
    {
        $contents = File::get($path);

        if (str_contains($contents, 'payment/*/success')) {
            $this->components->twoColumnDetail(self::CSRF_LABEL, '<fg=yellow>already present — skipped</>');
            return;
        }

        // Only patch if ->withMiddleware() block exists; otherwise show manual instructions
        if (! str_contains($contents, '->withMiddleware(')) {
            $this->components->twoColumnDetail(self::CSRF_LABEL, '<fg=red>withMiddleware() not found — add manually</>');
            $this->showBootstrapManualInstructions();
            return;
        }

        $this->components->task('Adding CSRF exclusions (bootstrap/app.php)', function () use ($path, $contents) {
            $inject = <<<'PHP'

    $middleware->validateCsrfTokens(except: [
        'payment/*/success',
        'payment/*/error',
        'api/payment/webhook/*',
    ]);
PHP;

            // Inject as first statement inside ->withMiddleware(function (Middleware $middleware) {
            $patched = preg_replace(
                '/->withMiddleware\(function\s*\(\s*Middleware\s+\$middleware\s*\)\s*\{/',
                "->withMiddleware(function (Middleware \$middleware) {{$inject}",
                $contents,
            );

            File::put($path, $patched ?? $contents);
        });
    }

    private function showBootstrapManualInstructions(): void
    {
        $this->newLine();
        $this->line('  <fg=yellow>Add CSRF exclusions manually in bootstrap/app.php:</>');
        $this->line('  <fg=gray>->withMiddleware(function (Middleware $middleware) {</>');
        $this->line("  <fg=cyan>    \$middleware->validateCsrfTokens(except: [</>") ;
        $this->line("  <fg=cyan>        'payment/*/success',</>") ;
        $this->line("  <fg=cyan>        'payment/*/error',</>") ;
        $this->line("  <fg=cyan>        'api/payment/webhook/*',</>") ;
        $this->line("  <fg=cyan>    ]);</>") ;
        $this->line('  <fg=gray>})</>');
    }

    private function printEnvStub(): void
    {
        $envPath = base_path('.env');

        if (! File::exists($envPath)) {
            return;
        }

        $envContents = File::get($envPath);

        if (str_contains($envContents, 'PAYMENT_GATEWAY')) {
            return;
        }

        $this->components->task('Adding .env stubs', function () use ($envPath) {
            $stub = <<<'ENV'


# ── Payment Engine ──────────────────────────────────
PAYMENT_GATEWAY=knet

# KNET (Kuwait)
KNET_TRANSPORT_ID=
KNET_TRANSPORT_PASSWORD=
KNET_RESOURCE_KEY=
KNET_SUCCESS_URL="${APP_URL}/payment/knet/success"
KNET_ERROR_URL="${APP_URL}/payment/knet/error"
KNET_SANDBOX=true

# Fawry (Egypt) — optional
# FAWRY_MERCHANT_CODE=
# FAWRY_SECURE_KEY=
# FAWRY_RETURN_URL="${APP_URL}/payment/fawry/success"
# FAWRY_SANDBOX=true

# Stripe (optional)
# STRIPE_SECRET_KEY=sk_test_...
# STRIPE_WEBHOOK_SECRET=whsec_...

# PayPal (optional)
# PAYPAL_CLIENT_ID=
# PAYPAL_CLIENT_SECRET=
# ────────────────────────────────────────────────────
ENV;
            File::append($envPath, $stub);
        });
    }

    private function printNextSteps(): void
    {
        $this->newLine();
        $this->components->success('Payment Engine installed successfully!');
        $this->newLine();
        $this->line('  <fg=white;options=bold>Next Steps:</>');
        $this->newLine();
        $this->line('  <fg=gray>1.</> Fill gateway credentials in <fg=cyan>.env</>');
        $this->line('  <fg=gray>2.</> Start queue workers:');
        $this->line('       <fg=cyan>php artisan queue:work --queue=payment-webhooks</>');
        $this->line('       <fg=cyan>php artisan queue:work --queue=payment-reconcile</>');
        $this->line('       <fg=cyan>php artisan queue:work --queue=payment-recovery</>');
        $this->line('  <fg=gray>3.</> Listen to events in <fg=cyan>EventServiceProvider::boot()</>');
        $this->line('  <fg=gray>4.</> Docs: <fg=cyan>https://mostafax2.github.io/payment-engine/</>');
        $this->newLine();
    }

    private function packageVersion(): string
    {
        $composerPath = __DIR__ . '/../../../composer.json';

        if (! file_exists($composerPath)) {
            return '2.x';
        }

        $data = json_decode((string) file_get_contents($composerPath), true);

        return $data['version'] ?? '2.x';
    }
}

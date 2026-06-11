<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Mostafax\PaymentEngine\Http\Controllers\WebhookController;

$prefix = config('payment-engine.route_prefix', 'payment');

// Gateway callbacks (web — no CSRF: added to VerifyCsrfToken exclusions)
Route::any("{$prefix}/{gateway}/success", [WebhookController::class, 'success'])->name('payment.success');
Route::any("{$prefix}/{gateway}/error",   [WebhookController::class, 'error'])->name('payment.error');

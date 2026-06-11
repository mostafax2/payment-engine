<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Mostafax\PaymentEngine\Http\Controllers\PaymentController;
use Mostafax\PaymentEngine\Http\Controllers\ReconciliationController;
use Mostafax\PaymentEngine\Http\Controllers\WebhookController;

$apiPrefix  = config('payment-engine.api_prefix', 'api/payment');
$middleware = config('payment-engine.api_middleware', ['api', 'auth:sanctum']);

Route::prefix($apiPrefix)->middleware($middleware)->group(function () {
    // Payments
    Route::post('initiate',              [PaymentController::class, 'initiate']);
    Route::get('transactions',           [PaymentController::class, 'index']);
    Route::get('transactions/{ulid}',    [PaymentController::class, 'show']);
    Route::post('inquire/{trackId}',     [PaymentController::class, 'inquire']);

    // Reconciliation
    Route::post('reconcile',                            [ReconciliationController::class, 'run']);
    Route::get('reconciliation/reports',                [ReconciliationController::class, 'reports']);
    Route::get('reconciliation/reports/{ulid}',         [ReconciliationController::class, 'show']);
});

// Webhook endpoint — no auth, but signature-verified
Route::prefix($apiPrefix)->middleware(['api'])->group(function () {
    Route::post('webhook/{gateway}', [WebhookController::class, 'receive'])->name('payment.webhook');
});

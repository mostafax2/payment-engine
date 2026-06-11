<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('payment-engine.tables.transactions', 'pe_transactions'), function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();

            // Multi-tenant
            $table->string('tenant_id')->nullable()->index();

            // Gateway
            $table->string('gateway', 50)->index();
            $table->string('gateway_transaction_id')->nullable()->index();

            // Identifiers
            $table->string('track_id')->unique();
            $table->string('order_id')->nullable()->index();

            // Financials
            $table->decimal('amount', 15, 3);
            $table->char('currency', 3)->default('KWD');

            // Status
            $table->string('status', 30)->default('initiated')->index();
            $table->string('gateway_status')->nullable();

            // Gateway response fields
            $table->string('reference_id')->nullable();
            $table->string('auth_code')->nullable();
            $table->string('post_date')->nullable();

            // Payloads (immutable raw data)
            $table->json('gateway_response')->nullable();
            $table->json('metadata')->nullable();

            // Lifecycle timestamps
            $table->timestamp('initiated_at')->useCurrent();
            $table->timestamp('captured_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('reconciled_at')->nullable();
            $table->timestamp('recovered_at')->nullable();

            $table->timestamps();

            $table->index(['gateway', 'status']);
            $table->index(['gateway', 'created_at']);
            $table->index(['tenant_id', 'gateway', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('payment-engine.tables.transactions', 'pe_transactions'));
    }
};

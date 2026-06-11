<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('payment-engine.tables.reconciliation_items', 'pe_reconciliation_items'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained(
                config('payment-engine.tables.reconciliation_reports', 'pe_reconciliation_reports')
            )->cascadeOnDelete();

            $table->string('issue_type', 40)->index();
            // missing_in_internal | missing_in_gateway | amount_mismatch | status_mismatch | duplicate

            $table->string('track_id')->nullable()->index();
            $table->string('gateway_transaction_id')->nullable();

            $table->decimal('gateway_amount', 15, 3)->nullable();
            $table->decimal('internal_amount', 15, 3)->nullable();

            $table->string('gateway_status')->nullable();
            $table->string('internal_status')->nullable();

            $table->json('details')->nullable();

            $table->boolean('auto_recovered')->default(false);
            $table->timestamp('recovered_at')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['report_id', 'issue_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('payment-engine.tables.reconciliation_items', 'pe_reconciliation_items'));
    }
};

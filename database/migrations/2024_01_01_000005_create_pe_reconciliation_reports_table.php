<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('payment-engine.tables.reconciliation_reports', 'pe_reconciliation_reports'), function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();

            $table->string('gateway', 50)->index();
            $table->string('tenant_id')->nullable()->index();
            $table->date('period_from');
            $table->date('period_to');

            // Counters
            $table->unsignedInteger('total_gateway_transactions')->default(0);
            $table->unsignedInteger('total_internal_transactions')->default(0);
            $table->unsignedInteger('matched_count')->default(0);
            $table->unsignedInteger('missing_in_internal')->default(0);
            $table->unsignedInteger('missing_in_gateway')->default(0);
            $table->unsignedInteger('amount_mismatch_count')->default(0);
            $table->unsignedInteger('status_mismatch_count')->default(0);

            // Financials
            $table->decimal('total_gateway_amount', 15, 3)->default(0);
            $table->decimal('total_internal_amount', 15, 3)->default(0);

            // Full report blob
            $table->json('report_data')->nullable();

            $table->string('status', 20)->default('pending')->index();
            $table->string('run_by')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamps();

            $table->index(['gateway', 'period_from', 'period_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('payment-engine.tables.reconciliation_reports', 'pe_reconciliation_reports'));
    }
};

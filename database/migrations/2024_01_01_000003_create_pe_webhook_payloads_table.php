<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('payment-engine.tables.webhook_payloads', 'pe_webhook_payloads'), function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();

            $table->string('gateway', 50)->index();
            $table->foreignId('transaction_id')
                ->nullable()
                ->constrained(config('payment-engine.tables.transactions', 'pe_transactions'))
                ->nullOnDelete();

            // Raw persistence — never modified after insert
            $table->longText('raw_body');
            $table->json('headers')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('http_method', 10)->default('POST');

            // Idempotency
            $table->string('idempotency_key')->unique()->index();
            $table->boolean('signature_verified')->default(false);

            // Processing state
            $table->string('processing_status', 30)->default('received')->index();
            $table->unsignedTinyInteger('attempt_count')->default(0);
            $table->timestamp('next_retry_at')->nullable()->index();
            $table->timestamp('processed_at')->nullable();

            $table->timestamps();

            $table->index(['gateway', 'processing_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('payment-engine.tables.webhook_payloads', 'pe_webhook_payloads'));
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('payment-engine.tables.webhook_attempts', 'pe_webhook_attempts'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_payload_id')->constrained(
                config('payment-engine.tables.webhook_payloads', 'pe_webhook_payloads')
            )->cascadeOnDelete();

            $table->unsignedTinyInteger('attempt_number');
            $table->boolean('success')->default(false);
            $table->text('exception_message')->nullable();
            $table->longText('exception_trace')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['webhook_payload_id', 'attempt_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('payment-engine.tables.webhook_attempts', 'pe_webhook_attempts'));
    }
};

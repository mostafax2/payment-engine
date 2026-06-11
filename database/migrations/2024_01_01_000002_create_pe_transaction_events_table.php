<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('payment-engine.tables.transaction_events', 'pe_transaction_events'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained(
                config('payment-engine.tables.transactions', 'pe_transactions')
            )->cascadeOnDelete();

            $table->string('event_type', 60)->index();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->string('actor', 60)->default('system');
            $table->json('payload')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['transaction_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('payment-engine.tables.transaction_events', 'pe_transaction_events'));
    }
};

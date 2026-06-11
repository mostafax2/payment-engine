<?php

declare(strict_types=1);

namespace Mostafax\PaymentEngine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Mostafax\PaymentEngine\Enums\WebhookStatus;

class WebhookPayload extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'headers'            => 'array',
        'signature_verified' => 'boolean',
        'processing_status'  => WebhookStatus::class,
        'processed_at'       => 'datetime',
        'next_retry_at'      => 'datetime',
    ];

    public function getTable(): string
    {
        return config('payment-engine.tables.webhook_payloads', 'pe_webhook_payloads');
    }

    protected static function booting(): void
    {
        static::creating(static function (self $model): void {
            $model->ulid ??= (string) Str::ulid();
        });
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(WebhookAttempt::class)->orderBy('attempt_number');
    }

    public function markProcessed(): void
    {
        $this->update([
            'processing_status' => WebhookStatus::Processed,
            'processed_at'      => now(),
        ]);
    }

    public function markFailed(string $reason, int $attemptNumber): void
    {
        $delays     = config('payment-engine.webhook.retry_delays', [60, 300, 900, 3600, 86400]);
        $maxAttempts= config('payment-engine.webhook.max_attempts', 5);
        $nextDelay  = $delays[$attemptNumber] ?? null;

        $this->update([
            'attempt_count'     => $attemptNumber,
            'processing_status' => $attemptNumber >= $maxAttempts
                ? WebhookStatus::DeadLetter
                : WebhookStatus::Failed,
            'next_retry_at'     => $nextDelay !== null ? now()->addSeconds($nextDelay) : null,
        ]);
    }

    public function isDuplicate(): bool
    {
        return $this->processing_status === WebhookStatus::Duplicate;
    }

    public function isDeadLetter(): bool
    {
        return $this->processing_status === WebhookStatus::DeadLetter;
    }

    public function scopePendingRetry($query): mixed
    {
        return $query
            ->where('processing_status', WebhookStatus::Failed->value)
            ->where('next_retry_at', '<=', now());
    }
}

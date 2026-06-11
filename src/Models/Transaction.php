<?php

declare(strict_types=1);

namespace Mostafax\PaymentEngine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Mostafax\PaymentEngine\Enums\TransactionStatus;

class Transaction extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'amount'           => 'float',
        'status'           => TransactionStatus::class,
        'gateway_response' => 'array',
        'metadata'         => 'array',
        'initiated_at'     => 'datetime',
        'captured_at'      => 'datetime',
        'failed_at'        => 'datetime',
        'reconciled_at'    => 'datetime',
        'recovered_at'     => 'datetime',
    ];

    public function getTable(): string
    {
        return config('payment-engine.tables.transactions', 'pe_transactions');
    }

    protected static function booting(): void
    {
        static::creating(static function (self $model): void {
            $model->ulid ??= (string) Str::ulid();
        });
    }

    public function events(): HasMany
    {
        return $this->hasMany(TransactionEvent::class)->orderBy('id');
    }

    public function webhooks(): HasMany
    {
        return $this->hasMany(WebhookPayload::class);
    }

    public function isCaptured(): bool
    {
        return $this->status === TransactionStatus::Captured;
    }

    public function isPending(): bool
    {
        return in_array($this->status, [TransactionStatus::Initiated, TransactionStatus::Pending], strict: true);
    }

    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    public function scopeForGateway($query, string $gateway): mixed
    {
        return $query->where('gateway', $gateway);
    }

    public function scopeForTenant($query, string $tenantId): mixed
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopePending($query): mixed
    {
        return $query->whereIn('status', [TransactionStatus::Initiated->value, TransactionStatus::Pending->value]);
    }

    public function scopeCaptured($query): mixed
    {
        return $query->where('status', TransactionStatus::Captured->value);
    }

    public function scopeCreatedBetween($query, string $from, string $to): mixed
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }
}

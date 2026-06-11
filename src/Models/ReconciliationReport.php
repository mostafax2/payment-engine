<?php

declare(strict_types=1);

namespace Mostafax\PaymentEngine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Mostafax\PaymentEngine\Enums\ReconciliationStatus;

class ReconciliationReport extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'period_from'            => 'date',
        'period_to'              => 'date',
        'status'                 => ReconciliationStatus::class,
        'report_data'            => 'array',
        'total_gateway_amount'   => 'float',
        'total_internal_amount'  => 'float',
        'completed_at'           => 'datetime',
    ];

    public function getTable(): string
    {
        return config('payment-engine.tables.reconciliation_reports', 'pe_reconciliation_reports');
    }

    protected static function booting(): void
    {
        static::creating(static function (self $model): void {
            $model->ulid ??= (string) Str::ulid();
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReconciliationItem::class, 'report_id');
    }

    public function hasMismatches(): bool
    {
        return $this->missing_in_internal > 0
            || $this->missing_in_gateway > 0
            || $this->amount_mismatch_count > 0
            || $this->status_mismatch_count > 0;
    }

    public function markCompleted(): void
    {
        $this->update([
            'status'       => ReconciliationStatus::Completed,
            'completed_at' => now(),
        ]);
    }

    public function markFailed(string $reason): void
    {
        $this->update([
            'status'        => ReconciliationStatus::Failed,
            'error_message' => $reason,
            'completed_at'  => now(),
        ]);
    }
}

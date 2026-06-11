<?php

declare(strict_types=1);

namespace Mostafax\PaymentEngine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReconciliationItem extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected $casts = [
        'gateway_amount'   => 'float',
        'internal_amount'  => 'float',
        'details'          => 'array',
        'auto_recovered'   => 'boolean',
        'recovered_at'     => 'datetime',
        'created_at'       => 'datetime',
    ];

    public function getTable(): string
    {
        return config('payment-engine.tables.reconciliation_items', 'pe_reconciliation_items');
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(ReconciliationReport::class);
    }
}

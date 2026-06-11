<?php

declare(strict_types=1);

namespace Mostafax\PaymentEngine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Mostafax\PaymentEngine\Enums\TransactionEventType;

class TransactionEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected $casts = [
        'event_type' => TransactionEventType::class,
        'payload'    => 'array',
        'created_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return config('payment-engine.tables.transaction_events', 'pe_transaction_events');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}

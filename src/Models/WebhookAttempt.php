<?php

declare(strict_types=1);

namespace Mostafax\PaymentEngine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookAttempt extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected $casts = [
        'success'    => 'boolean',
        'created_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return config('payment-engine.tables.webhook_attempts', 'pe_webhook_attempts');
    }

    public function webhookPayload(): BelongsTo
    {
        return $this->belongsTo(WebhookPayload::class);
    }
}

<?php

declare(strict_types=1);

namespace Mostafax\PaymentEngine\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Mostafax\PaymentEngine\Models\WebhookPayload;
use Mostafax\PaymentEngine\Services\WebhookProcessor;

final class ProcessWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries   = 1; // Retry logic is internal to WebhookProcessor
    public int $timeout = 60;

    public function __construct(public readonly int $webhookId) {}

    public function handle(WebhookProcessor $processor): void
    {
        $webhook = WebhookPayload::findOrFail($this->webhookId);
        $processor->process($webhook);
    }

    public function failed(\Throwable $exception): void
    {
        // WebhookProcessor already records the failure and schedules retry.
    }
}

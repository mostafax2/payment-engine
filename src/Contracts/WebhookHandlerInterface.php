<?php

declare(strict_types=1);

namespace Mostafax\PaymentEngine\Contracts;

use Illuminate\Http\Request;
use Mostafax\PaymentEngine\Models\WebhookPayload;

interface WebhookHandlerInterface
{
    /** Persist the raw HTTP request before any processing. */
    public function persist(Request $request, string $gateway): WebhookPayload;

    /** Verify the gateway's signature / HMAC. */
    public function verifySignature(Request $request, string $gateway): bool;

    /** Derive an idempotency key from the raw payload. */
    public function idempotencyKey(array $payload, string $gateway): string;

    /** Process the webhook payload, updating the transaction ledger. */
    public function process(WebhookPayload $webhook): void;
}

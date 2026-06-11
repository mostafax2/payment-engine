<?php

declare(strict_types=1);

namespace Mostafax\PaymentEngine\DTOs;

final readonly class InitiatePaymentDTO
{
    public function __construct(
        public string  $gateway,
        public string  $trackId,
        public float   $amount,
        public string  $currency,
        public string  $successUrl,
        public string  $errorUrl,
        public ?string $tenantId    = null,
        public ?string $orderId     = null,
        public ?string $description = null,
        public array   $metadata    = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            gateway:     $data['gateway'],
            trackId:     $data['track_id'],
            amount:      (float) $data['amount'],
            currency:    $data['currency'] ?? config('payment-engine.gateways.' . $data['gateway'] . '.currency', 'KWD'),
            successUrl:  $data['success_url'],
            errorUrl:    $data['error_url'],
            tenantId:    $data['tenant_id'] ?? null,
            orderId:     $data['order_id']  ?? null,
            description: $data['description'] ?? null,
            metadata:    $data['metadata'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'gateway'     => $this->gateway,
            'track_id'    => $this->trackId,
            'amount'      => $this->amount,
            'currency'    => $this->currency,
            'success_url' => $this->successUrl,
            'error_url'   => $this->errorUrl,
            'tenant_id'   => $this->tenantId,
            'order_id'    => $this->orderId,
            'description' => $this->description,
            'metadata'    => $this->metadata,
        ];
    }
}

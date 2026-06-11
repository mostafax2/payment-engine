<?php

declare(strict_types=1);

namespace Mostafax\PaymentEngine\DTOs;

use Mostafax\PaymentEngine\Enums\TransactionStatus;

final readonly class GatewayResponseDTO
{
    public function __construct(
        public bool              $success,
        public TransactionStatus $status,
        public ?string           $gatewayTransactionId = null,
        public ?string           $trackId              = null,
        public ?string           $referenceId          = null,
        public ?string           $authCode             = null,
        public ?float            $amount               = null,
        public ?string           $currency             = null,
        public ?string           $gatewayStatus        = null,
        public ?string           $postDate             = null,
        public array             $rawPayload           = [],
        public ?string           $errorMessage         = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            success:              $data['success'],
            status:               TransactionStatus::from($data['status']),
            gatewayTransactionId: $data['gateway_transaction_id'] ?? null,
            trackId:              $data['track_id']  ?? null,
            referenceId:          $data['reference_id'] ?? null,
            authCode:             $data['auth_code'] ?? null,
            amount:               isset($data['amount']) ? (float) $data['amount'] : null,
            currency:             $data['currency']  ?? null,
            gatewayStatus:        $data['gateway_status'] ?? null,
            postDate:             $data['post_date'] ?? null,
            rawPayload:           $data['raw_payload'] ?? [],
            errorMessage:         $data['error_message'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'success'               => $this->success,
            'status'                => $this->status->value,
            'gateway_transaction_id'=> $this->gatewayTransactionId,
            'track_id'              => $this->trackId,
            'reference_id'          => $this->referenceId,
            'auth_code'             => $this->authCode,
            'amount'                => $this->amount,
            'currency'              => $this->currency,
            'gateway_status'        => $this->gatewayStatus,
            'post_date'             => $this->postDate,
            'raw_payload'           => $this->rawPayload,
            'error_message'         => $this->errorMessage,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Mostafax\PaymentEngine\DTOs;

final readonly class ReconciliationResultDTO
{
    public function __construct(
        public string $gateway,
        public string $periodFrom,
        public string $periodTo,
        public int    $totalGateway,
        public int    $totalInternal,
        public int    $matched,
        public int    $missingInInternal,
        public int    $missingInGateway,
        public int    $amountMismatch,
        public int    $statusMismatch,
        public float  $totalGatewayAmount,
        public float  $totalInternalAmount,
        public array  $missingItems  = [],
        public array  $mismatchItems = [],
    ) {}

    public function toArray(): array
    {
        return [
            'gateway'               => $this->gateway,
            'period_from'           => $this->periodFrom,
            'period_to'             => $this->periodTo,
            'total_gateway'         => $this->totalGateway,
            'total_internal'        => $this->totalInternal,
            'matched'               => $this->matched,
            'missing_in_internal'   => $this->missingInInternal,
            'missing_in_gateway'    => $this->missingInGateway,
            'amount_mismatch'       => $this->amountMismatch,
            'status_mismatch'       => $this->statusMismatch,
            'total_gateway_amount'  => $this->totalGatewayAmount,
            'total_internal_amount' => $this->totalInternalAmount,
            'missing_items'         => $this->missingItems,
            'mismatch_items'        => $this->mismatchItems,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            gateway:             $data['gateway'],
            periodFrom:          $data['period_from'],
            periodTo:            $data['period_to'],
            totalGateway:        (int)   $data['total_gateway'],
            totalInternal:       (int)   $data['total_internal'],
            matched:             (int)   $data['matched'],
            missingInInternal:   (int)   $data['missing_in_internal'],
            missingInGateway:    (int)   $data['missing_in_gateway'],
            amountMismatch:      (int)   $data['amount_mismatch'],
            statusMismatch:      (int)   $data['status_mismatch'],
            totalGatewayAmount:  (float) $data['total_gateway_amount'],
            totalInternalAmount: (float) $data['total_internal_amount'],
            missingItems:        $data['missing_items']  ?? [],
            mismatchItems:       $data['mismatch_items'] ?? [],
        );
    }
}

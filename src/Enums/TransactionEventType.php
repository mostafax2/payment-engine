<?php

declare(strict_types=1);

namespace Mostafax\PaymentEngine\Enums;

enum TransactionEventType: string
{
    case Initiated          = 'initiated';
    case RedirectedToGateway= 'redirected_to_gateway';
    case CallbackReceived   = 'callback_received';
    case WebhookReceived    = 'webhook_received';
    case StatusChecked      = 'status_checked';
    case StatusUpdated      = 'status_updated';
    case RecoveryAttempted  = 'recovery_attempted';
    case Recovered          = 'recovered';
    case Reconciled         = 'reconciled';
    case ReconciliationMismatch = 'reconciliation_mismatch';
    case RefundRequested    = 'refund_requested';
    case RefundCompleted    = 'refund_completed';
    case ManualOverride     = 'manual_override';
    case BackfillSynced     = 'backfill_synced';
}

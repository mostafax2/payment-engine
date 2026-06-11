<?php

declare(strict_types=1);

namespace Mostafax\PaymentEngine\Enums;

enum ReconciliationStatus: string
{
    case Pending   = 'pending';
    case Running   = 'running';
    case Completed = 'completed';
    case Failed    = 'failed';
}

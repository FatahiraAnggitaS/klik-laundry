<?php

namespace App\Enums;

enum PayoutStatus: string
{
    case PendingTransfer = 'pending_transfer';
    case Finalized = 'finalized';
    case Voided = 'voided';
}

<?php

namespace App\Enums;

enum PaymentReconciliation: string
{
    case NotRequired = 'not_required';
    case NeedsInquiry = 'needs_inquiry';
    case Matched = 'matched';
    case Mismatch = 'mismatch';
}

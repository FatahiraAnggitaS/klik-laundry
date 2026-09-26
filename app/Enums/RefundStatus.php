<?php

namespace App\Enums;

enum RefundStatus: string
{
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Completed = 'completed';
}

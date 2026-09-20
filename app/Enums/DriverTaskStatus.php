<?php

namespace App\Enums;

enum DriverTaskStatus: string
{
    case Pending = 'pending';
    case Offered = 'offered';
    case Accepted = 'accepted';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}

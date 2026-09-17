<?php

namespace App\Enums;

enum PayoutAccountStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case Superseded = 'superseded';
}

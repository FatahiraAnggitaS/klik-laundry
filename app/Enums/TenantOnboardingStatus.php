<?php

namespace App\Enums;

enum TenantOnboardingStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}

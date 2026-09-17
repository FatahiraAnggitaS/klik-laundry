<?php

namespace App\Enums;

enum TenantOperationalStatus: string
{
    case Inactive = 'inactive';
    case Active = 'active';
    case Suspended = 'suspended';
    case Closed = 'closed';
}

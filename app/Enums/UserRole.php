<?php

namespace App\Enums;

enum UserRole: string
{
    case Customer = 'customer';
    case TenantOwner = 'tenant_owner';
    case Driver = 'driver';
    case SuperUser = 'super_user';

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Customer',
            self::TenantOwner => 'Tenant owner',
            self::Driver => 'Driver',
            self::SuperUser => 'Super User',
        };
    }
}

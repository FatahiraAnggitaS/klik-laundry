<?php

namespace App\Enums;

enum PricingType: string
{
    case Fixed = 'fixed';
    case PerKg = 'per_kg';
}

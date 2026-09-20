<?php

namespace App\Enums;

enum DriverCommissionStatus: string
{
    case Earned = 'earned';
    case Paid = 'paid';
}

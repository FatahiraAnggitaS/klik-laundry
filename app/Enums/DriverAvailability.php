<?php

namespace App\Enums;

enum DriverAvailability: string
{
    case Available = 'available';
    case Unavailable = 'unavailable';
}

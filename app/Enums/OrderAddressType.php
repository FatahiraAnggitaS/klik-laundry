<?php

namespace App\Enums;

enum OrderAddressType: string
{
    case Pickup = 'pickup';
    case Delivery = 'delivery';
}

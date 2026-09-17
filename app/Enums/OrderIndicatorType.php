<?php

namespace App\Enums;

enum OrderIndicatorType: string
{
    case Delayed = 'delayed';
    case PickupDelayed = 'pickup_delayed';
    case DeliveryDelayed = 'delivery_delayed';
    case AwaitingCustomer = 'awaiting_customer';
}

<?php

namespace App\Enums;

enum FulfillmentStatus: string
{
    case AwaitingPayment = 'awaiting_payment';
    case AwaitingPickup = 'awaiting_pickup';
    case PickupAssigned = 'pickup_assigned';
    case PickedUp = 'picked_up';
    case AwaitingWeight = 'awaiting_weight';
    case Processing = 'processing';
    case ReadyForDelivery = 'ready_for_delivery';
    case DeliveryAssigned = 'delivery_assigned';
    case OutForDelivery = 'out_for_delivery';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }
}

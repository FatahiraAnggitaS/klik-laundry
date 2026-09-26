<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** @property int $driver_commission_id @property int $amount */
#[Fillable(['driver_payout_id', 'driver_commission_id', 'amount', 'active_commission_key', 'finalized_commission_key'])]
final class DriverPayoutItem extends Model
{
    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }
}

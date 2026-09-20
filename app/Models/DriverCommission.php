<?php

namespace App\Models;

use App\Enums\DriverCommissionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['public_id', 'tenant_id', 'task_id', 'driver_id', 'amount', 'status', 'earned_at', 'paid_at'])]
final class DriverCommission extends Model
{
    protected function casts(): array
    {
        return ['status' => DriverCommissionStatus::class, 'amount' => 'integer', 'earned_at' => 'immutable_datetime', 'paid_at' => 'immutable_datetime'];
    }
}

<?php

namespace App\Models;

use App\Enums\DriverCommissionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property int $tenant_id
 * @property int $task_id
 * @property int $driver_id
 * @property int $amount
 * @property DriverCommissionStatus $status
 * @property Carbon $earned_at
 * @property Carbon|null $paid_at
 * @property-read DeliveryTask $task
 */
#[Fillable(['public_id', 'tenant_id', 'task_id', 'driver_id', 'amount', 'status', 'earned_at', 'paid_at'])]
final class DriverCommission extends Model
{
    /** @return BelongsTo<DeliveryTask, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(DeliveryTask::class, 'task_id');
    }

    protected function casts(): array
    {
        return ['status' => DriverCommissionStatus::class, 'amount' => 'integer', 'earned_at' => 'immutable_datetime', 'paid_at' => 'immutable_datetime'];
    }
}

<?php

namespace App\Models;

use App\Enums\DriverOfferStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $task_id
 * @property int $driver_id
 * @property string $public_id
 * @property DriverOfferStatus $status
 * @property Carbon $expires_at
 * @property-read DeliveryTask $task
 */
#[Fillable(['public_id', 'task_id', 'driver_id', 'status', 'active_task_key', 'offered_at', 'expires_at', 'responded_at'])]
final class DriverTaskOffer extends Model
{
    /** @return BelongsTo<DeliveryTask, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(DeliveryTask::class, 'task_id');
    }

    protected function casts(): array
    {
        return ['status' => DriverOfferStatus::class, 'offered_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime', 'responded_at' => 'immutable_datetime'];
    }
}

<?php

namespace App\Models;

use App\Enums\DriverTaskStatus;
use App\Enums\DriverTaskType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $order_id
 * @property int $outlet_id
 * @property string $public_id
 * @property DriverTaskType $type
 * @property DriverTaskStatus $status
 * @property int|null $assignee_id
 * @property int $commission_amount
 * @property string|null $note
 * @property string|null $proof_disk
 * @property string|null $proof_key
 * @property string|null $proof_mime
 * @property int|null $proof_size
 * @property Carbon|null $proof_expires_at
 * @property Carbon|null $proof_access_revoked_at
 * @property Carbon $scheduled_starts_at
 * @property Carbon $scheduled_ends_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property-read Order $order
 * @property-read User|null $assignee
 * @property-read Collection<int, DriverTaskHistory> $histories
 */
#[Fillable(['public_id', 'tenant_id', 'order_id', 'outlet_id', 'type', 'status', 'assignee_id', 'active_driver_key', 'commission_amount', 'scheduled_starts_at', 'scheduled_ends_at', 'note', 'proof_disk', 'proof_key', 'proof_mime', 'proof_size', 'proof_expires_at', 'proof_access_revoked_at', 'offered_at', 'accepted_at', 'started_at', 'completed_at', 'cancelled_at'])]
final class DeliveryTask extends Model
{
    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /** @return HasMany<DriverTaskOffer, $this> */
    public function offers(): HasMany
    {
        return $this->hasMany(DriverTaskOffer::class, 'task_id');
    }

    /** @return HasMany<DriverTaskHistory, $this> */
    public function histories(): HasMany
    {
        return $this->hasMany(DriverTaskHistory::class, 'task_id');
    }

    protected function casts(): array
    {
        return [
            'type' => DriverTaskType::class, 'status' => DriverTaskStatus::class,
            'commission_amount' => 'integer', 'scheduled_starts_at' => 'immutable_datetime',
            'scheduled_ends_at' => 'immutable_datetime', 'offered_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime', 'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime', 'cancelled_at' => 'immutable_datetime',
            'proof_expires_at' => 'immutable_datetime', 'proof_access_revoked_at' => 'immutable_datetime',
        ];
    }
}

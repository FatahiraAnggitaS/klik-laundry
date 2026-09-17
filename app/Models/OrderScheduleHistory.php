<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $schedule_type
 * @property CarbonImmutable $old_starts_at
 * @property CarbonImmutable $old_ends_at
 * @property CarbonImmutable $new_starts_at
 * @property CarbonImmutable $new_ends_at
 * @property string|null $reason
 * @property CarbonImmutable $occurred_at
 */
#[Fillable(['order_id', 'schedule_type', 'old_slot_id', 'old_starts_at', 'old_ends_at', 'new_slot_id', 'new_starts_at', 'new_ends_at', 'actor_id', 'reason', 'occurred_at'])]
final class OrderScheduleHistory extends Model
{
    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    protected static function booted(): void
    {
        self::updating(static function (): never {
            throw new \LogicException('Order schedule history is append-only.');
        });
        self::deleting(static function (): never {
            throw new \LogicException('Order schedule history is append-only.');
        });
    }

    protected function casts(): array
    {
        return ['old_starts_at' => 'immutable_datetime', 'old_ends_at' => 'immutable_datetime', 'new_starts_at' => 'immutable_datetime', 'new_ends_at' => 'immutable_datetime', 'occurred_at' => 'immutable_datetime'];
    }
}

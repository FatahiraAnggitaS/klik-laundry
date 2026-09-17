<?php

namespace App\Models;

use App\Enums\FulfillmentStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property FulfillmentStatus|null $from_status
 * @property FulfillmentStatus $to_status
 * @property string|null $reason
 * @property CarbonImmutable $occurred_at
 */
#[Fillable(['order_id', 'from_status', 'to_status', 'actor_id', 'reason', 'occurred_at'])]
final class OrderStatusHistory extends Model
{
    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    protected static function booted(): void
    {
        self::updating(static function (): never {
            throw new \LogicException('Order status history is append-only.');
        });
        self::deleting(static function (): never {
            throw new \LogicException('Order status history is append-only.');
        });
    }

    protected function casts(): array
    {
        return ['from_status' => FulfillmentStatus::class, 'to_status' => FulfillmentStatus::class, 'occurred_at' => 'immutable_datetime'];
    }
}

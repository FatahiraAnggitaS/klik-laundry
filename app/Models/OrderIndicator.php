<?php

namespace App\Models;

use App\Enums\OrderIndicatorType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property OrderIndicatorType $type
 * @property array<string, int|string|null>|null $context
 * @property CarbonImmutable $detected_at
 * @property CarbonImmutable|null $resolved_at
 */
#[Fillable(['order_id', 'type', 'active_key', 'context', 'detected_at', 'resolved_at'])]
final class OrderIndicator extends Model
{
    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    protected function casts(): array
    {
        return ['type' => OrderIndicatorType::class, 'context' => 'array', 'detected_at' => 'immutable_datetime', 'resolved_at' => 'immutable_datetime'];
    }
}

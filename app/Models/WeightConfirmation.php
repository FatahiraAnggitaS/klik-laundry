<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property int $actual_grams
 * @property int $minimum_grams
 * @property int $billable_grams
 * @property int $items_subtotal
 * @property int $grand_total
 * @property string|null $proof_disk
 * @property string|null $proof_key
 * @property Carbon $confirmed_at
 */
#[Fillable(['public_id', 'order_id', 'actual_grams', 'minimum_grams', 'billable_grams', 'rounding_increment_grams', 'items_subtotal', 'grand_total', 'confirmed_by', 'reason', 'current_order_key', 'superseded_by', 'proof_disk', 'proof_key', 'proof_mime', 'proof_size', 'confirmed_at'])]
final class WeightConfirmation extends Model
{
    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    protected function casts(): array
    {
        return ['actual_grams' => 'integer', 'minimum_grams' => 'integer', 'billable_grams' => 'integer', 'items_subtotal' => 'integer', 'grand_total' => 'integer', 'confirmed_at' => 'immutable_datetime'];
    }
}

<?php

namespace App\Models;

use App\Enums\PricingType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $package_name
 * @property string|null $package_description
 * @property PricingType $pricing_type
 * @property int $unit_price
 * @property int|null $minimum_quantity
 * @property int|null $minimum_weight_grams
 * @property int $estimated_duration_minutes
 * @property int|null $quantity
 * @property int|null $estimated_weight_grams
 * @property int|null $estimated_billable_weight_grams
 * @property int|null $actual_weight_grams
 * @property int|null $billable_weight_grams
 */
#[Fillable(['order_id', 'package_id', 'package_name', 'package_description', 'pricing_type', 'unit_price', 'minimum_quantity', 'minimum_weight_grams', 'estimated_duration_minutes', 'quantity', 'estimated_weight_grams', 'estimated_billable_weight_grams', 'actual_weight_grams', 'billable_weight_grams'])]
final class OrderItem extends Model
{
    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    protected function casts(): array
    {
        return ['pricing_type' => PricingType::class, 'unit_price' => 'integer', 'minimum_quantity' => 'integer', 'minimum_weight_grams' => 'integer', 'estimated_duration_minutes' => 'integer', 'quantity' => 'integer', 'estimated_weight_grams' => 'integer', 'estimated_billable_weight_grams' => 'integer', 'actual_weight_grams' => 'integer', 'billable_weight_grams' => 'integer'];
    }
}

<?php

namespace App\Models;

use App\Enums\OrderAddressType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property OrderAddressType $type
 * @property string $label
 * @property string $contact_name
 * @property string $contact_phone
 * @property string $address
 * @property string $city
 * @property string $area
 * @property numeric-string $latitude
 * @property numeric-string $longitude
 */
#[Fillable(['order_id', 'type', 'source_public_id', 'label', 'contact_name', 'contact_phone', 'address', 'city', 'area', 'latitude', 'longitude'])]
final class OrderAddress extends Model
{
    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    protected function casts(): array
    {
        return ['type' => OrderAddressType::class, 'latitude' => 'decimal:7', 'longitude' => 'decimal:7'];
    }
}

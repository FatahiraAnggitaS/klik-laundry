<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property numeric-string $latitude
 * @property numeric-string $longitude
 * @property Carbon $location_consented_at
 */
#[Fillable([
    'public_id', 'customer_id', 'default_customer_id', 'label', 'contact_name',
    'contact_phone', 'address', 'city', 'area', 'latitude', 'longitude',
    'location_consented_at',
])]
final class CustomerAddress extends Model
{
    /** @return BelongsTo<User, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'location_consented_at' => 'datetime',
        ];
    }
}

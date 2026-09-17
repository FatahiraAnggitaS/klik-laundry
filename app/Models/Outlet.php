<?php

namespace App\Models;

use App\Enums\ResourceStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property ResourceStatus $status
 * @property numeric-string $latitude
 * @property numeric-string $longitude
 * @property Carbon|null $archived_at
 */
#[Fillable([
    'public_id', 'tenant_id', 'name', 'contact_phone', 'address', 'city', 'area',
    'latitude', 'longitude', 'service_radius_m', 'pickup_fee', 'delivery_fee',
    'status', 'archived_at',
])]
final class Outlet extends Model
{
    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return HasMany<OutletOperatingHour, $this> */
    public function operatingHours(): HasMany
    {
        return $this->hasMany(OutletOperatingHour::class);
    }

    /** @return HasMany<OutletSlot, $this> */
    public function slots(): HasMany
    {
        return $this->hasMany(OutletSlot::class);
    }

    /** @return HasMany<OutletBlackout, $this> */
    public function blackouts(): HasMany
    {
        return $this->hasMany(OutletBlackout::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ResourceStatus::class,
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'service_radius_m' => 'integer',
            'pickup_fee' => 'integer',
            'delivery_fee' => 'integer',
            'archived_at' => 'datetime',
        ];
    }
}

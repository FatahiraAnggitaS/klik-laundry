<?php

namespace App\Models;

use App\Enums\PricingType;
use App\Enums\ResourceStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property PricingType $pricing_type
 * @property ResourceStatus $status
 */
#[Fillable([
    'public_id', 'tenant_id', 'name', 'description', 'pricing_type', 'unit_price',
    'minimum_quantity', 'minimum_weight_grams', 'estimated_duration_minutes',
    'status', 'archived_at',
])]
final class ServicePackage extends Model
{
    protected $table = 'packages';

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'pricing_type' => PricingType::class,
            'status' => ResourceStatus::class,
            'unit_price' => 'integer',
            'minimum_quantity' => 'integer',
            'minimum_weight_grams' => 'integer',
            'estimated_duration_minutes' => 'integer',
            'archived_at' => 'datetime',
        ];
    }
}

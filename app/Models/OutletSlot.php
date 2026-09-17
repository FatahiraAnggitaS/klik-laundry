<?php

namespace App\Models;

use App\Enums\SlotType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property SlotType $type */
#[Fillable(['public_id', 'outlet_id', 'type', 'day_of_week', 'starts_at', 'ends_at', 'is_active'])]
final class OutletSlot extends Model
{
    /** @return BelongsTo<Outlet, $this> */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['type' => SlotType::class, 'day_of_week' => 'integer', 'is_active' => 'boolean'];
    }
}

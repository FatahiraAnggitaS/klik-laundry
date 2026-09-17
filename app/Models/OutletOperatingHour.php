<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['outlet_id', 'day_of_week', 'opens_at', 'closes_at'])]
final class OutletOperatingHour extends Model
{
    /** @return BelongsTo<Outlet, $this> */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['day_of_week' => 'integer'];
    }
}

<?php

namespace App\Models;

use App\Enums\DriverAvailability;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $user_id
 * @property int $tenant_id
 * @property DriverAvailability $availability
 */
#[Fillable(['user_id', 'tenant_id', 'availability'])]
final class DriverProfile extends Model
{
    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return ['availability' => DriverAvailability::class];
    }
}

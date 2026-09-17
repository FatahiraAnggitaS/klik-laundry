<?php

namespace App\Models;

use App\Enums\TenantOnboardingStatus;
use App\Enums\TenantOperationalStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property TenantOnboardingStatus $onboarding_status
 * @property TenantOperationalStatus $operational_status
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $closure_requested_at
 * @property Carbon|null $closed_at
 * @property bool $payout_hold
 * @property numeric-string $initial_outlet_latitude
 * @property numeric-string $initial_outlet_longitude
 */
#[Fillable([
    'public_id', 'name', 'slug', 'phone', 'onboarding_status', 'operational_status',
    'review_reason', 'reviewed_by', 'reviewed_at', 'closure_requested_at', 'closed_at',
    'payout_hold', 'payout_hold_reason', 'payout_hold_set_by', 'initial_outlet_name',
    'initial_outlet_address', 'initial_outlet_city', 'initial_outlet_area',
    'initial_outlet_latitude', 'initial_outlet_longitude',
])]
final class Tenant extends Model
{
    use HasFactory;

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return HasMany<TenantPayoutAccount, $this> */
    public function payoutAccounts(): HasMany
    {
        return $this->hasMany(TenantPayoutAccount::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'onboarding_status' => TenantOnboardingStatus::class,
            'operational_status' => TenantOperationalStatus::class,
            'reviewed_at' => 'datetime',
            'closure_requested_at' => 'datetime',
            'closed_at' => 'datetime',
            'payout_hold' => 'boolean',
            'initial_outlet_latitude' => 'decimal:7',
            'initial_outlet_longitude' => 'decimal:7',
        ];
    }
}

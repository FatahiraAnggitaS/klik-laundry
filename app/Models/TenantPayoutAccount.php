<?php

namespace App\Models;

use App\Enums\PayoutAccountStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property PayoutAccountStatus $verification_status
 * @property Carbon $created_at
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $superseded_at
 * @property Tenant $tenant
 */
#[Fillable([
    'public_id', 'tenant_id', 'current_tenant_id', 'bank_name', 'account_holder_name',
    'account_number', 'masked_account_number', 'verification_status', 'submitted_by',
    'reviewed_by', 'reviewed_at', 'review_reason', 'superseded_at',
])]
#[Hidden(['account_holder_name', 'account_number'])]
final class TenantPayoutAccount extends Model
{
    use HasFactory;

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'account_holder_name' => 'encrypted',
            'account_number' => 'encrypted',
            'verification_status' => PayoutAccountStatus::class,
            'reviewed_at' => 'datetime',
            'superseded_at' => 'datetime',
        ];
    }
}

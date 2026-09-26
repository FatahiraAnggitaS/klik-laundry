<?php

namespace App\Models;

use App\Enums\PayoutStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property string $batch_reference
 * @property int $tenant_id
 * @property int $payout_account_id
 * @property PayoutStatus $status
 * @property Carbon $cutoff_at
 * @property int $gross_amount
 * @property int $fee_amount
 * @property int $adjustment_amount
 * @property int $net_amount
 * @property string $bank_name
 * @property string $masked_account_number
 * @property string|null $transfer_method
 * @property string|null $external_reference
 * @property string|null $void_reason
 * @property Carbon $created_at
 * @property Carbon|null $finalized_at
 * @property-read Tenant $tenant
 */
#[Fillable(['public_id', 'batch_reference', 'tenant_id', 'payout_account_id', 'status', 'cutoff_at', 'gross_amount', 'fee_amount', 'adjustment_amount', 'net_amount', 'pending_tenant_key', 'bank_name', 'account_holder_name', 'account_number', 'masked_account_number', 'transfer_method', 'external_reference', 'void_reason', 'created_by', 'finalized_by', 'voided_by', 'finalized_at', 'voided_at'])]
#[Hidden(['account_holder_name', 'account_number'])]
final class TenantPayout extends Model
{
    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    protected function casts(): array
    {
        return [
            'status' => PayoutStatus::class,
            'cutoff_at' => 'immutable_datetime',
            'gross_amount' => 'integer',
            'fee_amount' => 'integer',
            'adjustment_amount' => 'integer',
            'net_amount' => 'integer',
            'account_holder_name' => 'encrypted',
            'account_number' => 'encrypted',
            'finalized_at' => 'immutable_datetime',
            'voided_at' => 'immutable_datetime',
        ];
    }
}

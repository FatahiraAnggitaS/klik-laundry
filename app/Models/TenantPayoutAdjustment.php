<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** @property int $financial_adjustment_id @property int $amount */
#[Fillable(['tenant_payout_id', 'financial_adjustment_id', 'amount', 'active_adjustment_key', 'finalized_adjustment_key'])]
final class TenantPayoutAdjustment extends Model
{
    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }
}

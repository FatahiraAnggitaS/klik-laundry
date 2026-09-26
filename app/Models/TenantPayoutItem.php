<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** @property int $payment_id @property int $gross_amount @property int $fee_amount @property int $net_amount */
#[Fillable(['tenant_payout_id', 'payment_id', 'gross_amount', 'fee_amount', 'net_amount', 'active_payment_key', 'finalized_payment_key'])]
final class TenantPayoutItem extends Model
{
    protected function casts(): array
    {
        return ['gross_amount' => 'integer', 'fee_amount' => 'integer', 'net_amount' => 'integer'];
    }
}

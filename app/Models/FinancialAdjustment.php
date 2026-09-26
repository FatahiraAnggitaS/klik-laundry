<?php

namespace App\Models;

use App\Enums\AdjustmentType;
use App\Enums\SettlementStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property int $tenant_id
 * @property int $payment_id
 * @property AdjustmentType $type
 * @property int $amount
 * @property SettlementStatus $settlement_status
 * @property Carbon $occurred_at
 * @property-read Payment $payment
 */
#[Fillable(['public_id', 'tenant_id', 'payment_id', 'refund_request_id', 'type', 'amount', 'reason', 'settlement_status', 'created_by', 'occurred_at', 'settled_at'])]
final class FinancialAdjustment extends Model
{
    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    protected static function booted(): void
    {
        self::deleting(static function (): never {
            throw new \LogicException('Financial adjustments are append-only.');
        });
    }

    protected function casts(): array
    {
        return ['type' => AdjustmentType::class, 'settlement_status' => SettlementStatus::class, 'amount' => 'integer', 'occurred_at' => 'immutable_datetime', 'settled_at' => 'immutable_datetime'];
    }
}

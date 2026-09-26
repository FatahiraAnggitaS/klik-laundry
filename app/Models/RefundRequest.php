<?php

namespace App\Models;

use App\Enums\RefundStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property int $tenant_id
 * @property int $order_id
 * @property int $payment_id
 * @property RefundStatus $status
 * @property int $amount
 * @property string $reason
 * @property string|null $review_reason
 * @property string|null $transfer_method
 * @property string|null $external_reference
 * @property Carbon $created_at
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $completed_at
 * @property-read Payment $payment
 * @property-read Order $order
 */
#[Fillable(['public_id', 'tenant_id', 'order_id', 'payment_id', 'status', 'amount', 'reason', 'active_payment_key', 'completed_payment_key', 'submitted_by', 'reviewed_by', 'review_reason', 'reviewed_at', 'completed_by', 'transfer_method', 'external_reference', 'completed_at'])]
final class RefundRequest extends Model
{
    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    protected function casts(): array
    {
        return ['status' => RefundStatus::class, 'amount' => 'integer', 'reviewed_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime'];
    }
}

<?php

namespace App\Models;

use App\Enums\PaymentReconciliation;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property int $tenant_id
 * @property int $order_id
 * @property string|null $active_order_key
 * @property string $merchant_order_id
 * @property string|null $provider_reference
 * @property string $channel_code
 * @property int $amount
 * @property PaymentStatus $status
 * @property PaymentReconciliation $reconciliation
 * @property string|null $provider_payment_url
 * @property int|null $fee_amount
 * @property Carbon $expires_at
 * @property Carbon|null $paid_at
 * @property Carbon|null $last_inquired_at
 */
#[Fillable(['public_id', 'tenant_id', 'order_id', 'active_order_key', 'merchant_order_id', 'provider_reference', 'channel_code', 'amount', 'status', 'reconciliation', 'provider_payment_url', 'fee_amount', 'expires_at', 'paid_at', 'terminal_at', 'last_inquired_at'])]
final class Payment extends Model
{
    protected $guarded = ['*'];

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return HasMany<PaymentEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(PaymentEvent::class);
    }

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'fee_amount' => 'integer',
            'status' => PaymentStatus::class,
            'reconciliation' => PaymentReconciliation::class,
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'terminal_at' => 'datetime',
            'last_inquired_at' => 'datetime',
        ];
    }
}

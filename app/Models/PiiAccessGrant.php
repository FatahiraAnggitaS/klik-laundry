<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $public_id
 * @property int $actor_id
 * @property int $order_id
 * @property int $customer_id
 * @property int $actor_auth_version
 * @property CarbonImmutable $expires_at
 */
#[Fillable(['public_id', 'actor_id', 'order_id', 'customer_id', 'actor_auth_version', 'session_hash', 'reason', 'granted_at', 'expires_at', 'revoked_at'])]
final class PiiAccessGrant extends Model
{
    public $timestamps = false;

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    protected function casts(): array
    {
        return [
            'actor_auth_version' => 'integer',
            'granted_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }
}

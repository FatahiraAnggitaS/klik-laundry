<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['payment_id', 'fingerprint', 'provider_status', 'provider_reference', 'amount', 'signature_ok', 'processed_at'])]
final class PaymentEvent extends Model
{
    protected $guarded = ['*'];

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'signature_ok' => 'boolean',
            'processed_at' => 'datetime',
        ];
    }
}

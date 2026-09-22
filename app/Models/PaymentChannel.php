<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $channel_code
 * @property string $label
 * @property string $category
 * @property bool $is_active
 * @property Carbon|null $verified_at
 */
final class PaymentChannel extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }
}

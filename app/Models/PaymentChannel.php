<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

<?php

namespace App\Models;

use App\Enums\PiiAccessEvent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['grant_id', 'actor_id', 'order_id', 'customer_id', 'event', 'occurred_at'])]
final class PiiAccessLog extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['event' => PiiAccessEvent::class, 'occurred_at' => 'immutable_datetime'];
    }
}

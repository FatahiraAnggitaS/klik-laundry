<?php

namespace App\Models;

use App\Enums\DriverTaskStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property DriverTaskStatus|null $from_status
 * @property DriverTaskStatus $to_status
 * @property string|null $reason
 * @property Carbon $occurred_at
 */
#[Fillable(['task_id', 'from_status', 'to_status', 'actor_id', 'reason', 'occurred_at'])]
final class DriverTaskHistory extends Model
{
    protected static function booted(): void
    {
        self::updating(static fn (): never => throw new \LogicException('Driver task history is append-only.'));
        self::deleting(static fn (): never => throw new \LogicException('Driver task history is append-only.'));
    }

    protected function casts(): array
    {
        return ['from_status' => DriverTaskStatus::class, 'to_status' => DriverTaskStatus::class, 'occurred_at' => 'immutable_datetime'];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $action
 * @property string $subject_type
 * @property string $subject_id
 * @property string|null $reason
 * @property Carbon $created_at
 */
#[Fillable(['tenant_id', 'actor_id', 'action', 'subject_type', 'subject_id', 'reason', 'before', 'after', 'created_at'])]
final class ActivityLog extends Model
{
    public $timestamps = false;

    protected static function booted(): void
    {
        self::updating(static function (): never {
            throw new \LogicException('Activity logs are append-only.');
        });
        self::deleting(static function (): never {
            throw new \LogicException('Activity logs are append-only.');
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'created_at' => 'datetime',
        ];
    }
}

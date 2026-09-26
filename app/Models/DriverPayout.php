<?php

namespace App\Models;

use App\Enums\PayoutStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property string $batch_reference
 * @property int $tenant_id
 * @property int $driver_id
 * @property PayoutStatus $status
 * @property Carbon $cutoff_at
 * @property int $total_amount
 * @property string|null $transfer_method
 * @property string|null $external_reference
 * @property string|null $note
 * @property string|null $void_reason
 * @property Carbon|null $finalized_at
 */
#[Fillable(['public_id', 'batch_reference', 'tenant_id', 'driver_id', 'status', 'cutoff_at', 'total_amount', 'pending_driver_key', 'transfer_method', 'external_reference', 'note', 'void_reason', 'created_by', 'finalized_by', 'voided_by', 'finalized_at', 'voided_at'])]
final class DriverPayout extends Model
{
    protected function casts(): array
    {
        return ['status' => PayoutStatus::class, 'cutoff_at' => 'immutable_datetime', 'total_amount' => 'integer', 'finalized_at' => 'immutable_datetime', 'voided_at' => 'immutable_datetime'];
    }
}

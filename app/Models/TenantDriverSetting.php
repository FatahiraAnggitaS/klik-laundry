<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int|null $pickup_commission
 * @property int|null $delivery_commission
 */
#[Fillable(['tenant_id', 'pickup_commission', 'delivery_commission', 'updated_by'])]
final class TenantDriverSetting extends Model
{
    protected function casts(): array
    {
        return ['pickup_commission' => 'integer', 'delivery_commission' => 'integer'];
    }
}

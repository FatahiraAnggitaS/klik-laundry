<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class PlatformSetting extends Model
{
    public const GLOBAL_KEY = 'global';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'max_service_radius_km' => 'integer',
            'payment_maintenance_enabled' => 'boolean',
            'version' => 'integer',
        ];
    }
}

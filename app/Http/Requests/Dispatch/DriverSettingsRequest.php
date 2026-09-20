<?php

namespace App\Http\Requests\Dispatch;

final class DriverSettingsRequest extends TenantDriverRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'pickup_commission' => ['required', 'integer', 'min:0'],
            'delivery_commission' => ['required', 'integer', 'min:0'],
        ];
    }
}

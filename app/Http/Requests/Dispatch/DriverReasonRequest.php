<?php

namespace App\Http\Requests\Dispatch;

final class DriverReasonRequest extends TenantDriverRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:5', 'max:1000']];
    }
}

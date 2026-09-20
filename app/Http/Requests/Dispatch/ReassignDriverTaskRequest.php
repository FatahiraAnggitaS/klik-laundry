<?php

namespace App\Http\Requests\Dispatch;

final class ReassignDriverTaskRequest extends TenantDriverRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['driver_public_id' => ['required', 'string', 'size:26'], 'reason' => ['required', 'string', 'min:5', 'max:1000']];
    }
}

<?php

namespace App\Http\Requests\Dispatch;

final class InviteDriverRequest extends TenantDriverRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['email' => ['required', 'email:rfc', 'max:255'], 'phone' => ['required', 'string', 'max:30']];
    }
}

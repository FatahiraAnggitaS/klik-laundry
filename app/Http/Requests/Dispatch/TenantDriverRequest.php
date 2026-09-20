<?php

namespace App\Http\Requests\Dispatch;

use App\Enums\UserRole;
use App\Http\Requests\Identity\AuthenticatedIdentityRequest;

class TenantDriverRequest extends AuthenticatedIdentityRequest
{
    public function authorize(): bool
    {
        return $this->identity()->role() === UserRole::TenantOwner;
    }
}

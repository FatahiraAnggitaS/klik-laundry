<?php

namespace App\Http\Requests\SuperUser;

use App\Enums\UserRole;
use App\Http\Requests\Identity\AuthenticatedIdentityRequest;

final class ShowPiiPrivacyRequest extends AuthenticatedIdentityRequest
{
    public function authorize(): bool
    {
        return $this->identity()->role() === UserRole::SuperUser;
    }

    public function rules(): array
    {
        return [];
    }
}

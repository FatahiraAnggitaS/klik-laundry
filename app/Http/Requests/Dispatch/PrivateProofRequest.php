<?php

namespace App\Http\Requests\Dispatch;

use App\Http\Requests\Identity\AuthenticatedIdentityRequest;

final class PrivateProofRequest extends AuthenticatedIdentityRequest
{
    public function authorize(): bool
    {
        return true;
    }
}

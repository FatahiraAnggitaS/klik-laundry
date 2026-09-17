<?php

namespace App\Http\Requests\Identity;

final class ConfirmSensitiveAuthenticationRequest extends AuthenticatedIdentityRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string'],
            'code' => ['required', 'digits:6'],
        ];
    }
}

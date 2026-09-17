<?php

namespace App\Http\Requests\SuperUser;

use App\Http\Requests\Identity\AuthenticatedIdentityRequest;

final class PlatformReasonRequest extends AuthenticatedIdentityRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-platform') ?? false;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:10', 'max:1000']];
    }
}

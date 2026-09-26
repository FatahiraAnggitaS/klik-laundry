<?php

namespace App\Http\Requests\Finance;

use App\Http\Requests\Identity\AuthenticatedIdentityRequest;

final class VoidPayoutRequest extends AuthenticatedIdentityRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:5', 'max:1000']];
    }
}

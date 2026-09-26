<?php

namespace App\Http\Requests\Finance;

use App\Http\Requests\Identity\AuthenticatedIdentityRequest;

final class SubmitRefundRequest extends AuthenticatedIdentityRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-own-tenant') ?? false;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['payment_public_id' => ['required', 'string', 'size:26'], 'reason' => ['required', 'string', 'min:5', 'max:1000']];
    }
}

<?php

namespace App\Http\Requests\Payments;

use App\Http\Requests\Identity\AuthenticatedIdentityRequest;

final class InquirePaymentRequest extends AuthenticatedIdentityRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('manage-platform') ?? false) || ($this->user()?->can('manage-own-tenant') ?? false);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [];
    }
}

<?php

namespace App\Http\Requests\Payments;

use App\Http\Requests\Identity\AuthenticatedIdentityRequest;

final class ShowPaymentRequest extends AuthenticatedIdentityRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [];
    }
}

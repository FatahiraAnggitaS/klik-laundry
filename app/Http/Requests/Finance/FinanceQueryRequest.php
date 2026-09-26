<?php

namespace App\Http\Requests\Finance;

use App\Http\Requests\Identity\AuthenticatedIdentityRequest;

final class FinanceQueryRequest extends AuthenticatedIdentityRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'], 'outlet' => ['nullable', 'string', 'size:26']];
    }
}

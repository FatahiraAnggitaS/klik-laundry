<?php

namespace App\Http\Requests\Finance;

use App\Enums\TransferMethod;
use App\Http\Requests\Identity\AuthenticatedIdentityRequest;
use Illuminate\Validation\Rule;

final class FinalizePayoutRequest extends AuthenticatedIdentityRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'method' => ['required', Rule::enum(TransferMethod::class)],
            'reference' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:1000'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}

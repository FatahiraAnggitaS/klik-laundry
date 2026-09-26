<?php

namespace App\Http\Requests\Finance;

use App\Enums\TransferMethod;
use App\Http\Requests\Identity\AuthenticatedIdentityRequest;
use Illuminate\Validation\Rule;

final class CompleteRefundRequest extends AuthenticatedIdentityRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-platform') ?? false;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['method' => ['required', Rule::enum(TransferMethod::class), Rule::notIn([TransferMethod::NoTransferRequired->value])], 'reference' => ['required', 'string', 'max:120'], 'reason' => ['required', 'string', 'min:5', 'max:1000']];
    }
}

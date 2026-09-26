<?php

namespace App\Http\Requests\Finance;

use App\Enums\RefundStatus;
use App\Http\Requests\Identity\AuthenticatedIdentityRequest;
use Illuminate\Validation\Rule;

final class ReviewRefundRequest extends AuthenticatedIdentityRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-platform') ?? false;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['decision' => ['required', Rule::in([RefundStatus::Approved->value, RefundStatus::Rejected->value])], 'reason' => ['required', 'string', 'min:5', 'max:1000']];
    }
}

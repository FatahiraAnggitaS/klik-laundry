<?php

namespace App\Http\Requests\SuperUser;

use App\Enums\PayoutAccountStatus;
use App\Http\Requests\Identity\AuthenticatedIdentityRequest;
use Illuminate\Validation\Rule;

final class ReviewPayoutAccountRequest extends AuthenticatedIdentityRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-platform') ?? false;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::enum(PayoutAccountStatus::class)->only([PayoutAccountStatus::Verified, PayoutAccountStatus::Rejected])],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }
}

<?php

namespace App\Http\Requests\SuperUser;

use App\Enums\TenantOnboardingStatus;
use App\Http\Requests\Identity\AuthenticatedIdentityRequest;
use Illuminate\Validation\Rule;

final class ReviewTenantRequest extends AuthenticatedIdentityRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-platform') ?? false;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::enum(TenantOnboardingStatus::class)->only([TenantOnboardingStatus::Approved, TenantOnboardingStatus::Rejected])],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }
}

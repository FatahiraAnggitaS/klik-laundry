<?php

namespace App\Http\Requests\Dispatch;

use App\Enums\DriverAvailability;
use App\Enums\UserRole;
use App\Http\Requests\Identity\AuthenticatedIdentityRequest;
use Illuminate\Validation\Rule;

final class DriverAvailabilityRequest extends AuthenticatedIdentityRequest
{
    public function authorize(): bool
    {
        return $this->identity()->role() === UserRole::Driver;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['availability' => ['required', Rule::enum(DriverAvailability::class)]];
    }
}

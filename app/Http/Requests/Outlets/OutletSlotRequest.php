<?php

namespace App\Http\Requests\Outlets;

use App\Enums\SlotType;
use App\Http\Requests\Identity\AuthenticatedIdentityRequest;
use Illuminate\Validation\Rule;

final class OutletSlotRequest extends AuthenticatedIdentityRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-own-tenant') === true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(SlotType::class)],
            'day_of_week' => ['required', 'integer', 'between:0,6'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}

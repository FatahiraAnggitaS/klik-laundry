<?php

namespace App\Http\Requests\Dispatch;

use App\Enums\DriverTaskType;
use Illuminate\Validation\Rule;

final class OfferDriverTaskRequest extends TenantDriverRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'driver_public_id' => ['required', 'string', 'size:26'],
            'type' => ['sometimes', Rule::enum(DriverTaskType::class)],
        ];
    }
}

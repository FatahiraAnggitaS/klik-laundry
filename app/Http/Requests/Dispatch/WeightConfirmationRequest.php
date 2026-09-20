<?php

namespace App\Http\Requests\Dispatch;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

final class WeightConfirmationRequest extends TenantDriverRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'actual_grams' => ['required', 'integer', 'min:1', 'max:1000000'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'proof' => ['nullable', File::image()->dimensions(Rule::dimensions()->maxWidth(4096)->maxHeight(4096))->types(['jpg', 'jpeg', 'png', 'webp'])->max('5mb')],
        ];
    }
}

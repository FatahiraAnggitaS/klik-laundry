<?php

namespace App\Http\Requests\Dispatch;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

final class CompleteDriverTaskRequest extends DriverTaskRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:500'],
            'proof' => ['nullable', File::image()->dimensions(Rule::dimensions()->maxWidth(4096)->maxHeight(4096))->types(['jpg', 'jpeg', 'png', 'webp'])->max('5mb')],
        ];
    }
}

<?php

namespace App\Http\Requests\Outlets;

use Illuminate\Foundation\Http\FormRequest;

final class ShowOutletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'pickup_address' => ['nullable', 'string', 'size:26'],
            'delivery_address' => ['nullable', 'string', 'size:26'],
        ];
    }
}

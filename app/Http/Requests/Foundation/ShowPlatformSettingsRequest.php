<?php

namespace App\Http\Requests\Foundation;

use Illuminate\Foundation\Http\FormRequest;

final class ShowPlatformSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}

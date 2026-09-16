<?php

namespace App\Http\Requests\MilestoneZero;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ShowWireflowPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'role' => $this->route('role'),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::enum(UserRole::class)],
            'step' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9-]+$/'],
        ];
    }

    public function role(): UserRole
    {
        return UserRole::from($this->validated('role'));
    }

    public function step(): ?string
    {
        $step = $this->validated('step');

        return is_string($step) ? $step : null;
    }
}

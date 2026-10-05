<?php

namespace App\Http\Requests\Identity;

use Closure;

final class ShowSensitiveAuthenticationRequest extends AuthenticatedIdentityRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'return_to' => [
                'nullable',
                'string',
                'max:2048',
                static function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value)
                        || ! str_starts_with($value, '/')
                        || str_starts_with($value, '//')
                        || str_contains($value, '\\')
                        || preg_match('/[\r\n]/', $value) === 1) {
                        $fail('Tujuan kembali tidak valid.');
                    }
                },
            ],
        ];
    }
}

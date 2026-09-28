<?php

namespace App\Http\Requests\SuperUser;

use App\Enums\UserRole;
use App\Http\Requests\Identity\AuthenticatedIdentityRequest;

final class AuditReviewRequest extends AuthenticatedIdentityRequest
{
    public function authorize(): bool
    {
        return $this->identity()->role() === UserRole::SuperUser;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'action' => ['nullable', 'string', 'max:100'],
            'actor' => ['nullable', 'string', 'max:26'],
            'tenant' => ['nullable', 'string', 'max:26'],
            'subject' => ['nullable', 'string', 'max:64'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    /** @return array<string, string|null> */
    public function filters(): array
    {
        $validated = $this->validated();

        return collect(['action', 'actor', 'tenant', 'subject', 'from', 'to'])
            ->mapWithKeys(fn (string $key): array => [$key => isset($validated[$key]) && $validated[$key] !== '' ? (string) $validated[$key] : null])
            ->all();
    }
}

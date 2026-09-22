<?php

namespace App\Http\Requests\Payments;

use App\Enums\PaymentReconciliation;
use App\Enums\PaymentStatus;
use App\Http\Requests\Identity\AuthenticatedIdentityRequest;
use Illuminate\Validation\Rule;

final class TenantPaymentQueryRequest extends AuthenticatedIdentityRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-own-tenant') ?? false;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(PaymentStatus::class)],
            'reconciliation' => ['nullable', Rule::enum(PaymentReconciliation::class)],
            'query' => ['nullable', 'string', 'max:64'],
        ];
    }

    /** @return array{status: string|null, reconciliation: string|null, query: string|null} */
    public function filters(): array
    {
        return [
            'status' => $this->validated('status'),
            'reconciliation' => $this->validated('reconciliation'),
            'query' => $this->validated('query'),
        ];
    }
}

<?php

namespace App\Http\Requests\Identity;

use App\Enums\UserRole;
use Illuminate\Validation\Rule;

final class CloseCustomerAccountRequest extends AuthenticatedIdentityRequest
{
    public function authorize(): bool
    {
        return $this->identity()->role() === UserRole::Customer;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['confirmation' => ['required', 'string', Rule::in(['TUTUP AKUN'])]];
    }
}

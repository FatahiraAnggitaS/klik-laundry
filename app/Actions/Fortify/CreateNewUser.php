<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Services\Identity\RegisterCustomerService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

final readonly class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function __construct(private RegisterCustomerService $service) {}

    /** @param array<string, string> $input */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9][0-9\s-]{7,28}$/'],
            'password' => $this->passwordRules(),
        ])->validate();

        $user = $this->service->handle(
            name: trim($input['name']),
            email: mb_strtolower(trim($input['email'])),
            phone: trim($input['phone']),
            password: $input['password'],
        );

        return User::query()->findOrFail($user->databaseId());
    }
}

<?php

namespace App\Actions\Fortify;

use App\Contracts\IdentityUser;
use App\Services\Identity\ChangePasswordService;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

final readonly class UpdateUserPassword implements UpdatesUserPasswords
{
    use PasswordValidationRules;

    public function __construct(private ChangePasswordService $service) {}

    /** @param array<string, string> $input */
    public function update(IdentityUser $user, array $input): void
    {
        Validator::make($input, [
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => $this->passwordRules(),
        ])->validateWithBag('updatePassword');

        $this->service->handle($user, $input['password'], false);
    }
}

<?php

namespace App\Actions\Fortify;

use App\Contracts\IdentityUser;
use App\Services\Identity\ChangePasswordService;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

final readonly class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    public function __construct(private ChangePasswordService $service) {}

    /** @param array<string, string> $input */
    public function reset(IdentityUser $user, array $input): void
    {
        Validator::make($input, ['password' => $this->passwordRules()])->validate();
        $this->service->handle($user, $input['password'], true);
    }
}

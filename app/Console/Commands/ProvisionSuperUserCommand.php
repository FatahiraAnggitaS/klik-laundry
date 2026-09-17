<?php

namespace App\Console\Commands;

use App\Exceptions\Domain\DomainActionConflict;
use App\Services\Identity\ProvisionSuperUserService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

final class ProvisionSuperUserCommand extends Command
{
    protected $signature = 'super-user:provision';

    protected $description = 'Provision the single Super User through secure interactive prompts';

    public function handle(ProvisionSuperUserService $service): int
    {
        if ($service->exists()) {
            $this->error('Super User sudah tersedia. Provisioning kedua ditolak.');

            return self::FAILURE;
        }

        $name = (string) $this->ask('Nama lengkap');
        $email = mb_strtolower(trim((string) $this->ask('Email')));
        $phone = trim((string) $this->ask('Nomor telepon'));
        $password = (string) $this->secret('Password');
        $confirmation = (string) $this->secret('Konfirmasi password');

        Validator::make([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'password' => $password,
            'password_confirmation' => $confirmation,
        ], [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9][0-9\s-]{7,28}$/'],
            'password' => ['required', 'string', Password::min(12)->mixedCase()->numbers(), 'confirmed'],
        ])->validate();

        try {
            $user = $service->handle($name, $email, $phone, $password);
        } catch (DomainActionConflict $exception) {
            $this->error($exception->safeMessage());

            return self::FAILURE;
        }
        event(new Registered($user));

        $this->info('Super User dibuat. Verifikasi email dan aktifkan 2FA sebelum mengakses administrasi.');

        return self::SUCCESS;
    }
}

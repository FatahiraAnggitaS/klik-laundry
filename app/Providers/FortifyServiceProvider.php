<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Identity\AuthenticateUserService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;

final class FortifyServiceProvider extends ServiceProvider
{
    public function boot(AuthenticateUserService $authentication): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);

        Fortify::authenticateUsing(function (Request $request) use ($authentication): ?User {
            $user = $authentication->handle((string) $request->input('email'), (string) $request->input('password'));

            if ($user instanceof User && $user->role() === UserRole::TenantOwner) {
                $request->merge(['remember' => true]);
            }

            return $user instanceof User ? $user : null;
        });

        Fortify::loginView(fn () => Inertia::render('auth/login'));
        Fortify::registerView(fn () => Inertia::render('auth/register'));
        Fortify::requestPasswordResetLinkView(fn () => Inertia::render('auth/forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/reset-password', [
            'email' => $request->string('email')->toString(),
            'token' => $request->route('token'),
        ]));
        Fortify::verifyEmailView(fn () => Inertia::render('auth/verify-email'));
        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password'));
        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/two-factor-challenge'));

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(
            Str::lower($request->string('email')->toString()).'|'.$request->ip(),
        ));
        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute(5)->by(
            (string) $request->session()->get('login.id', $request->ip()),
        ));
        RateLimiter::for('verification', fn (Request $request) => Limit::perMinute(6)->by(
            (string) ($request->user()?->getAuthIdentifier() ?? $request->ip()),
        ));
    }
}

<?php

namespace App\Http\Middleware;

use App\Contracts\IdentityUser;
use App\Enums\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class EnsureActiveIdentitySession
{
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $user = $request->user();
        $sessionVersion = $request->session()->get('auth.version');

        if (! $user instanceof IdentityUser
            || $user->status() !== UserStatus::Active
            || ! is_int($sessionVersion)
            || $sessionVersion !== $user->authVersion()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', 'Sesi telah dicabut. Silakan login kembali.');
        }

        return $next($request);
    }
}

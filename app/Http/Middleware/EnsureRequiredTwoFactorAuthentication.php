<?php

namespace App\Http\Middleware;

use App\Contracts\IdentityUser;
use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureRequiredTwoFactorAuthentication
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof IdentityUser
            && in_array($user->role(), [UserRole::TenantOwner, UserRole::SuperUser], true)
            && ! $user->hasConfirmedTwoFactorAuthentication()) {
            return redirect()->route('identity.security')->with('status', 'Aktifkan 2FA sebelum mengakses fitur ini.');
        }

        return $next($request);
    }
}

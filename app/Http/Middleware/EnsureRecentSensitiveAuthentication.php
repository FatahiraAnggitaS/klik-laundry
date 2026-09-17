<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureRecentSensitiveAuthentication
{
    public function handle(Request $request, Closure $next): Response
    {
        $confirmedAt = $request->session()->get('auth.sensitive_confirmed_at');
        $timeout = (int) config('auth.password_timeout', 900);

        if (! is_int($confirmedAt) || time() - $confirmedAt > $timeout) {
            $request->session()->put('url.intended', route('workspace'));

            return redirect()->route('identity.sensitive-authentication.create');
        }

        return $next($request);
    }
}

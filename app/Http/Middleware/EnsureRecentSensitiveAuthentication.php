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
            $request->session()->put('url.intended', $this->returnUrl($request));
            $request->session()->flash('status', 'Tindakan sebelumnya belum dijalankan. Setelah konfirmasi, kirim ulang tindakan tersebut.');

            return redirect()->route('identity.sensitive-authentication.create');
        }

        return $next($request);
    }

    private function returnUrl(Request $request): string
    {
        $referer = $request->headers->get('referer');
        $refererHost = is_string($referer) ? parse_url($referer, PHP_URL_HOST) : null;

        if (is_string($referer) && is_string($refererHost) && hash_equals($request->getHost(), $refererHost)) {
            $path = parse_url($referer, PHP_URL_PATH);
            $query = parse_url($referer, PHP_URL_QUERY);

            if (is_string($path) && str_starts_with($path, '/')) {
                return $path.(is_string($query) ? '?'.$query : '');
            }
        }

        return route('workspace');
    }
}

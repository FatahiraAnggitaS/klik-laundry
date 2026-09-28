<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

final readonly class ThrottleFortifyPasswordResetRequests
{
    public function __construct(private ThrottleRequests $throttle) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->route()?->getName(), ['password.email', 'password.update'], true)) {
            return $next($request);
        }

        return $this->throttle->handle($request, $next, 'password-reset');
    }
}

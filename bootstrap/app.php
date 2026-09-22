<?php

use App\Exceptions\Domain\DomainException;
use App\Http\Middleware\EnsureActiveIdentitySession;
use App\Http\Middleware\EnsureRecentSensitiveAuthentication;
use App\Http\Middleware\EnsureRequiredTwoFactorAuthentication;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Inertia\Inertia;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'identity.active' => EnsureActiveIdentitySession::class,
            'two-factor.required' => EnsureRequiredTwoFactorAuthentication::class,
            'sensitive.confirmed' => EnsureRecentSensitiveAuthentication::class,
        ]);

        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'webhooks/duitku',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (DomainException $exception, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(
                    ['message' => $exception->safeMessage()],
                    $exception->httpStatus(),
                );
            }

            return Inertia::render('errors/domain', [
                'status' => $exception->httpStatus(),
                'message' => $exception->safeMessage(),
            ])->toResponse($request)->setStatusCode($exception->httpStatus());
        });
    })->create();

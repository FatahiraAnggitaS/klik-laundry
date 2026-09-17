<?php

namespace App\Http\Middleware;

use App\Contracts\IdentityUser;
use Illuminate\Http\Request;
use Inertia\Middleware;

final class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'app' => [
                'name' => config('app.name'),
                'locale' => 'id-ID',
                'timezone' => config('app.timezone'),
            ],
            'auth' => [
                'user' => $user instanceof IdentityUser ? [
                    'publicId' => $user->publicId(),
                    'name' => $user->displayName(),
                    'role' => $user->role()->value,
                ] : null,
            ],
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
            ],
        ];
    }
}

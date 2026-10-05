<?php

namespace App\Http\Middleware;

use App\Contracts\IdentityUser;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
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
        $sensitiveConfirmedAt = $request->session()->get('auth.sensitive_confirmed_at');
        $sensitiveAuthenticationConfirmed = is_int($sensitiveConfirmedAt)
            && time() - $sensitiveConfirmedAt <= (int) config('auth.password_timeout', 900);

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
            'theme' => [
                'defaultPreference' => 'system',
            ],
            'sensitiveAuthentication' => [
                'confirmed' => $sensitiveAuthenticationConfirmed,
            ],
            'notifications' => [
                'unreadCount' => fn (): int => $user instanceof IdentityUser
                    && Schema::hasTable('notifications')
                    ? app(NotificationRepositoryInterface::class)->unreadCount($user->databaseId())
                    : 0,
            ],
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
            ],
        ];
    }
}

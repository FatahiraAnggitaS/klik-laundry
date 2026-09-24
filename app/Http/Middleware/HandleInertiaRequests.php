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
            'notifications' => [
                'unreadCount' => fn (): int => $user instanceof IdentityUser
                    && ($request->routeIs('orders.show')
                        || $request->routeIs('tenant.orders.show')
                        || $request->routeIs('driver.tasks.index')
                        || $request->routeIs('tenant.dispatch.index')
                        || $request->routeIs('notifications.*')
                        || $request->routeIs('workspace'))
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

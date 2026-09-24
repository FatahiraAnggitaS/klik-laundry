<?php

namespace App\Http\Controllers\Notifications;

use App\Http\Requests\Notifications\NotificationRequest;
use App\Services\Notifications\ManageNotificationsService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final readonly class NotificationController
{
    public function __construct(private ManageNotificationsService $notifications) {}

    public function index(NotificationRequest $request): Response
    {
        return Inertia::render('notifications/index', ['notifications' => $this->notifications->paginate($request->identity())]);
    }

    public function read(NotificationRequest $request, string $notification): RedirectResponse
    {
        $this->notifications->markRead($request->identity(), $notification);

        return back();
    }

    public function readAll(NotificationRequest $request): RedirectResponse
    {
        $this->notifications->markAllRead($request->identity());

        return back()->with('status', 'Semua notifikasi ditandai sudah dibaca.');
    }
}

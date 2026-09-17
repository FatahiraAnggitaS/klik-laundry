<?php

namespace App\Http\Controllers\SuperUser;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperUser\PlatformReasonRequest;
use App\Services\Identity\ChangeUserStatusService;
use Illuminate\Http\RedirectResponse;

final class SuspendUserController extends Controller
{
    public function __construct(private readonly ChangeUserStatusService $service) {}

    public function __invoke(PlatformReasonRequest $request, string $user): RedirectResponse
    {
        $this->service->handle($request->identity(), $user, UserStatus::Suspended, $request->validated('reason'));

        return back()->with('status', 'Akun ditangguhkan dan sesinya dicabut.');
    }
}

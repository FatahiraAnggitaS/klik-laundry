<?php

namespace App\Http\Controllers\Dispatch;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dispatch\DriverReasonRequest;
use App\Http\Requests\Dispatch\DriverSettingsRequest;
use App\Http\Requests\Dispatch\InviteDriverRequest;
use App\Http\Requests\Dispatch\TenantDriverRequest;
use App\Services\Dispatch\GetDriverManagementService;
use App\Services\Dispatch\ManageDriverInvitationService;
use App\Services\Dispatch\ManageDriverService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class TenantDriverController extends Controller
{
    public function index(TenantDriverRequest $request, GetDriverManagementService $service): Response
    {
        return Inertia::render('tenant/drivers', $service->handle($request->identity()));
    }

    public function invite(InviteDriverRequest $request, ManageDriverInvitationService $service): RedirectResponse
    {
        $service->invite($request->identity(), $request->string('email')->toString(), $request->string('phone')->toString());

        return back()->with('status', 'Undangan Driver dikirim dan berlaku 48 jam.');
    }

    public function revoke(TenantDriverRequest $request, string $invitation, ManageDriverInvitationService $service): RedirectResponse
    {
        $service->revoke($request->identity(), $invitation);

        return back()->with('status', 'Undangan Driver dibatalkan.');
    }

    public function settings(DriverSettingsRequest $request, ManageDriverService $service): RedirectResponse
    {
        $service->updateSettings($request->identity(), $request->integer('pickup_commission'), $request->integer('delivery_commission'));

        return back()->with('status', 'Tarif komisi disimpan untuk task baru.');
    }

    public function deactivate(DriverReasonRequest $request, string $driver, ManageDriverService $service): RedirectResponse
    {
        $service->deactivate($request->identity(), $driver, $request->string('reason')->toString());

        return back()->with('status', 'Driver dinonaktifkan dan seluruh sesi dicabut.');
    }

    public function reactivate(DriverReasonRequest $request, string $driver, ManageDriverService $service): RedirectResponse
    {
        $service->reactivate($request->identity(), $driver, $request->string('reason')->toString());

        return back()->with('status', 'Driver diaktifkan kembali dengan availability unavailable.');
    }
}

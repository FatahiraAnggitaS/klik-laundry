<?php

namespace App\Http\Controllers\Foundation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Foundation\UpdateMaximumServiceRadiusRequest;
use App\Http\Requests\Payments\UpdatePaymentMaintenanceRequest;
use App\Http\Requests\SuperUser\ShowPlatformRequest;
use App\Services\Foundation\GetPlatformSettingsService;
use App\Services\Foundation\UpdateMaximumServiceRadiusService;
use App\Services\Payments\ManagePaymentChannelsService;
use App\Services\Payments\UpdatePaymentMaintenanceService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class ManagePlatformSettingsController extends Controller
{
    public function __construct(
        private readonly GetPlatformSettingsService $query,
        private readonly UpdateMaximumServiceRadiusService $command,
        private readonly ManagePaymentChannelsService $channels,
        private readonly UpdatePaymentMaintenanceService $paymentMaintenance,
    ) {}

    public function show(ShowPlatformRequest $request): Response
    {
        return Inertia::render('super-user/platform-settings', [
            ...$this->query->handle()->toArray(),
            'paymentChannels' => $this->channels->channels($request->identity()),
        ]);
    }

    public function update(UpdateMaximumServiceRadiusRequest $request): RedirectResponse
    {
        $this->command->handle(
            $request->identity(),
            $request->integer('maximum_service_radius_km'),
            $request->string('reason')->toString(),
        );

        return back()->with('status', 'Batas radius layanan berhasil diperbarui.');
    }

    public function updatePaymentMaintenance(UpdatePaymentMaintenanceRequest $request): RedirectResponse
    {
        $this->paymentMaintenance->handle(
            $request->identity(),
            (bool) $request->validated('enabled'),
            (string) $request->validated('reason'),
        );

        return back()->with('status', 'Mode maintenance pembayaran diperbarui.');
    }
}

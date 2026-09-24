<?php

namespace App\Http\Controllers\Dispatch;

use App\Enums\DriverAvailability;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dispatch\CompleteDriverTaskRequest;
use App\Http\Requests\Dispatch\DriverAvailabilityRequest;
use App\Http\Requests\Dispatch\DriverTaskRequest;
use App\Services\Dispatch\GetDriverTaskDashboardService;
use App\Services\Dispatch\ManageDriverService;
use App\Services\Dispatch\ProgressDriverTaskService;
use App\Services\Dispatch\RespondDriverOfferService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class DriverTaskController extends Controller
{
    public function index(DriverTaskRequest $request, GetDriverTaskDashboardService $service): Response
    {
        return Inertia::render('driver/tasks', $service->handle($request->identity()));
    }

    public function availability(DriverAvailabilityRequest $request, ManageDriverService $service): RedirectResponse
    {
        $service->updateAvailability($request->identity(), DriverAvailability::from($request->string('availability')->toString()));

        return back()->with('status', 'Availability diperbarui.');
    }

    public function accept(DriverTaskRequest $request, string $offer, RespondDriverOfferService $service): RedirectResponse
    {
        $service->handle($request->identity(), $offer, true);

        return back()->with('status', 'Offer diterima. Detail Customer tersedia sampai task selesai.');
    }

    public function reject(DriverTaskRequest $request, string $offer, RespondDriverOfferService $service): RedirectResponse
    {
        $service->handle($request->identity(), $offer, false);

        return back()->with('status', 'Offer ditolak.');
    }

    public function start(DriverTaskRequest $request, string $task, ProgressDriverTaskService $service): RedirectResponse
    {
        $service->start($request->identity(), $task);

        return back()->with('status', 'Task dimulai.');
    }

    public function complete(CompleteDriverTaskRequest $request, string $task, ProgressDriverTaskService $service): RedirectResponse
    {
        $service->complete($request->identity(), $task, $request->string('note')->toString() ?: null, $request->file('proof'));

        return back()->with('status', 'Task selesai dan komisi tercatat.');
    }
}

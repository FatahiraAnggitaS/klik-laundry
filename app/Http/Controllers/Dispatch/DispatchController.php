<?php

namespace App\Http\Controllers\Dispatch;

use App\Enums\DriverTaskType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dispatch\OfferDriverTaskRequest;
use App\Http\Requests\Dispatch\ReassignDriverTaskRequest;
use App\Http\Requests\Dispatch\TenantDriverRequest;
use App\Services\Dispatch\GetDispatchDashboardService;
use App\Services\Dispatch\OfferDriverTaskService;
use App\Services\Dispatch\ReassignDriverTaskService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class DispatchController extends Controller
{
    public function index(TenantDriverRequest $request, GetDispatchDashboardService $service): Response
    {
        return Inertia::render('tenant/dispatch', $service->handle($request->identity()));
    }

    public function offer(OfferDriverTaskRequest $request, string $order, OfferDriverTaskService $service): RedirectResponse
    {
        $service->handle($request->identity(), $order, $request->string('driver_public_id')->toString(), DriverTaskType::from($request->string('type', 'pickup')->toString()));

        return back()->with('status', 'Task ditawarkan selama 10 menit.');
    }

    public function reassign(ReassignDriverTaskRequest $request, string $task, ReassignDriverTaskService $service): RedirectResponse
    {
        $service->handle($request->identity(), $task, $request->string('driver_public_id')->toString(), $request->string('reason')->toString());

        return back()->with('status', 'Task di-reassign dan offer baru dibuat.');
    }
}

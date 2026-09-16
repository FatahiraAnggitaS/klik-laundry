<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\ShowDashboardPreviewRequest;
use App\Services\Dashboard\GetDashboardPreviewService;
use Inertia\Inertia;
use Inertia\Response;

final class ShowDashboardPreviewController extends Controller
{
    public function __construct(
        private readonly GetDashboardPreviewService $service,
    ) {}

    public function __invoke(ShowDashboardPreviewRequest $request): Response
    {
        return Inertia::render(
            'dashboard/index',
            $this->service->handle($request->role())->toArray(),
        );
    }
}

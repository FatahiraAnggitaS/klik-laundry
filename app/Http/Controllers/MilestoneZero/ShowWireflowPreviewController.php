<?php

namespace App\Http\Controllers\MilestoneZero;

use App\Http\Controllers\Controller;
use App\Http\Requests\MilestoneZero\ShowWireflowPreviewRequest;
use App\Services\MilestoneZero\GetWireflowPreviewService;
use Inertia\Inertia;
use Inertia\Response;

final class ShowWireflowPreviewController extends Controller
{
    public function __construct(
        private readonly GetWireflowPreviewService $service,
    ) {}

    public function __invoke(ShowWireflowPreviewRequest $request): Response
    {
        $wireflow = $this->service->handle($request->role(), $request->step());

        abort_if($wireflow === null, 404);

        return Inertia::render('milestone-zero/wireflow', $wireflow->toArray());
    }
}

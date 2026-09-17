<?php

namespace App\Http\Controllers\Identity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\ShowWorkspaceRequest;
use App\Services\Identity\GetIdentitySummaryService;
use Inertia\Inertia;
use Inertia\Response;

final class ShowSecuritySettingsController extends Controller
{
    public function __construct(private readonly GetIdentitySummaryService $service) {}

    public function __invoke(ShowWorkspaceRequest $request): Response
    {
        return Inertia::render('identity/security', [
            'identity' => $this->service->handle($request->identity())->toArray(),
        ]);
    }
}

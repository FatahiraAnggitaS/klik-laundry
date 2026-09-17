<?php

namespace App\Http\Controllers\Identity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\ShowWorkspaceRequest;
use App\Services\Tenancy\GetTenantWorkspaceService;
use Inertia\Inertia;
use Inertia\Response;

final class ShowWorkspaceController extends Controller
{
    public function __construct(private readonly GetTenantWorkspaceService $service) {}

    public function __invoke(ShowWorkspaceRequest $request): Response
    {
        return Inertia::render('identity/workspace', $this->service->handle($request->identity()));
    }
}

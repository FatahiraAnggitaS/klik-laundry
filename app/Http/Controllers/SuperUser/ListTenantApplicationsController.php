<?php

namespace App\Http\Controllers\SuperUser;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperUser\ShowPlatformRequest;
use App\Services\Tenancy\ListTenantApplicationsService;
use Inertia\Inertia;
use Inertia\Response;

final class ListTenantApplicationsController extends Controller
{
    public function __construct(private readonly ListTenantApplicationsService $service) {}

    public function __invoke(ShowPlatformRequest $request): Response
    {
        return Inertia::render('super-user/tenants', $this->service->handle($request->identity()));
    }
}

<?php

namespace App\Http\Controllers\Outlets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Outlets\TenantOperationRequest;
use App\Services\Outlets\GetTenantOperationsService;
use Inertia\Inertia;
use Inertia\Response;

final class TenantOperationsController extends Controller
{
    public function __construct(private readonly GetTenantOperationsService $service) {}

    public function __invoke(TenantOperationRequest $request): Response
    {
        return Inertia::render('tenant/operations', $this->service->handle($request->identity()));
    }
}

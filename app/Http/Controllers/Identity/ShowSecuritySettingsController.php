<?php

namespace App\Http\Controllers\Identity;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\ShowWorkspaceRequest;
use App\Services\Identity\GetIdentitySummaryService;
use App\Services\Privacy\GetAccountClosureReadinessService;
use Inertia\Inertia;
use Inertia\Response;

final class ShowSecuritySettingsController extends Controller
{
    public function __construct(
        private readonly GetIdentitySummaryService $service,
        private readonly GetAccountClosureReadinessService $closure,
    ) {}

    public function __invoke(ShowWorkspaceRequest $request): Response
    {
        $identity = $request->identity();

        return Inertia::render('identity/security', [
            'identity' => $this->service->handle($identity)->toArray(),
            'accountClosure' => $identity->role() === UserRole::Customer
                ? $this->closure->handle($identity)->toArray()
                : null,
        ]);
    }
}

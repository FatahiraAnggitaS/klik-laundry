<?php

namespace App\Http\Controllers\SuperUser;

use App\Enums\TenantOnboardingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperUser\ReviewTenantRequest;
use App\Services\Tenancy\ReviewTenantApplicationService;
use Illuminate\Http\RedirectResponse;

final class ReviewTenantApplicationController extends Controller
{
    public function __construct(private readonly ReviewTenantApplicationService $service) {}

    public function __invoke(ReviewTenantRequest $request, string $tenant): RedirectResponse
    {
        $this->service->handle(
            $request->identity(),
            $tenant,
            TenantOnboardingStatus::from($request->validated('decision')),
            $request->validated('reason'),
        );

        return back()->with('status', 'Review Tenant tersimpan.');
    }
}

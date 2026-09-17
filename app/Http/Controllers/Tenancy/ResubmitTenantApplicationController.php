<?php

namespace App\Http\Controllers\Tenancy;

use App\DTOs\Tenancy\TenantResubmissionData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenancy\ResubmitTenantApplicationRequest;
use App\Services\Tenancy\ResubmitTenantApplicationService;
use Illuminate\Http\RedirectResponse;

final class ResubmitTenantApplicationController extends Controller
{
    public function __construct(private readonly ResubmitTenantApplicationService $service) {}

    public function __invoke(ResubmitTenantApplicationRequest $request): RedirectResponse
    {
        $this->service->handle($request->identity(), new TenantResubmissionData(
            businessName: trim($request->validated('business_name')),
            phone: trim($request->validated('phone')),
            outletName: trim($request->validated('outlet_name')),
            outletAddress: trim($request->validated('outlet_address')),
            city: trim($request->validated('city')),
            area: trim($request->validated('area')),
            latitude: (string) $request->validated('latitude'),
            longitude: (string) $request->validated('longitude'),
        ));

        return back()->with('status', 'Pendaftaran Tenant diajukan ulang.');
    }
}

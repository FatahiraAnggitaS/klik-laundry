<?php

namespace App\Http\Controllers\Tenancy;

use App\DTOs\Tenancy\TenantRegistrationData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenancy\StoreTenantRegistrationRequest;
use App\Services\Tenancy\RegisterTenantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

final class StoreTenantRegistrationController extends Controller
{
    public function __construct(private readonly RegisterTenantService $service) {}

    public function __invoke(StoreTenantRegistrationRequest $request): RedirectResponse
    {
        $user = $this->service->handle(new TenantRegistrationData(
            businessName: trim($request->validated('business_name')),
            ownerName: trim($request->validated('owner_name')),
            email: mb_strtolower(trim($request->validated('email'))),
            phone: trim($request->validated('phone')),
            password: $request->validated('password'),
            outletName: trim($request->validated('outlet_name')),
            outletAddress: trim($request->validated('outlet_address')),
            city: trim($request->validated('city')),
            area: trim($request->validated('area')),
            latitude: (string) $request->validated('latitude'),
            longitude: (string) $request->validated('longitude'),
        ));

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect()->route('verification.notice');
    }
}

<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\CreateTenantPayoutRequest;
use App\Http\Requests\Finance\FinalizePayoutRequest;
use App\Http\Requests\Finance\FinanceQueryRequest;
use App\Http\Requests\Finance\VoidPayoutRequest;
use App\Services\Finance\GetFinanceDashboardService;
use App\Services\Finance\ManageTenantPayoutService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class TenantPayoutController extends Controller
{
    public function __construct(private readonly ManageTenantPayoutService $service, private readonly GetFinanceDashboardService $dashboard) {}

    public function show(FinanceQueryRequest $request, string $payout): Response
    {
        return Inertia::render('finance/payout-detail', ['kind' => 'tenant', 'payout' => $this->dashboard->tenantPayoutDetail($request->identity(), $payout)]);
    }

    public function store(CreateTenantPayoutRequest $request): RedirectResponse
    {
        $this->service->create($request->identity(), (string) $request->validated('tenant_public_id'), (string) $request->validated('cutoff'));

        return back()->with('status', 'Batch payout Tenant dibuat dari seluruh source eligible.');
    }

    public function finalize(FinalizePayoutRequest $request, string $payout): RedirectResponse
    {
        $this->service->finalize($request->identity(), $payout, (string) $request->validated('method'), (string) $request->validated('reference'), (string) $request->validated('reason'));

        return back()->with('status', 'Payout Tenant difinalisasi.');
    }

    public function void(VoidPayoutRequest $request, string $payout): RedirectResponse
    {
        $this->service->void($request->identity(), $payout, (string) $request->validated('reason'));

        return back()->with('status', 'Payout Tenant dibatalkan dan source dilepas.');
    }
}

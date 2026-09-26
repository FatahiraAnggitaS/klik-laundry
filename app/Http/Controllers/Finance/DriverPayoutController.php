<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\CreateDriverPayoutRequest;
use App\Http\Requests\Finance\FinalizePayoutRequest;
use App\Http\Requests\Finance\FinanceQueryRequest;
use App\Http\Requests\Finance\VoidPayoutRequest;
use App\Services\Finance\GetFinanceDashboardService;
use App\Services\Finance\ManageDriverPayoutService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class DriverPayoutController extends Controller
{
    public function __construct(private readonly ManageDriverPayoutService $service, private readonly GetFinanceDashboardService $dashboard) {}

    public function show(FinanceQueryRequest $request, string $payout): Response
    {
        return Inertia::render('finance/payout-detail', ['kind' => 'driver', 'payout' => $this->dashboard->driverPayoutDetail($request->identity(), $payout)]);
    }

    public function store(CreateDriverPayoutRequest $request): RedirectResponse
    {
        $this->service->create($request->identity(), (string) $request->validated('driver_public_id'), (string) $request->validated('cutoff'));

        return back()->with('status', 'Batch komisi Driver berhasil dibuat.');
    }

    public function finalize(FinalizePayoutRequest $request, string $payout): RedirectResponse
    {
        $this->service->finalize($request->identity(), $payout, (string) $request->validated('method'), $request->validated('reference'), $request->validated('note'), (string) $request->validated('reason'));

        return back()->with('status', 'Payout Driver difinalisasi.');
    }

    public function void(VoidPayoutRequest $request, string $payout): RedirectResponse
    {
        $this->service->void($request->identity(), $payout, (string) $request->validated('reason'));

        return back()->with('status', 'Payout Driver dibatalkan dan komisi dilepas.');
    }
}

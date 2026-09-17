<?php

namespace App\Http\Controllers\Tenancy;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenancy\SubmitPayoutAccountRequest;
use App\Services\Tenancy\SubmitPayoutAccountService;
use Illuminate\Http\RedirectResponse;

final class SubmitPayoutAccountController extends Controller
{
    public function __construct(private readonly SubmitPayoutAccountService $service) {}

    public function __invoke(SubmitPayoutAccountRequest $request): RedirectResponse
    {
        $this->service->handle(
            $request->identity(),
            trim($request->validated('bank_name')),
            trim($request->validated('account_holder_name')),
            $request->validated('account_number'),
        );

        return back()->with('status', 'Rekening payout dikirim untuk verifikasi.');
    }
}

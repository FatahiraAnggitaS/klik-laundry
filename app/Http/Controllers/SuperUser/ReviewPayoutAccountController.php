<?php

namespace App\Http\Controllers\SuperUser;

use App\Enums\PayoutAccountStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperUser\ReviewPayoutAccountRequest;
use App\Services\Tenancy\ReviewPayoutAccountService;
use Illuminate\Http\RedirectResponse;

final class ReviewPayoutAccountController extends Controller
{
    public function __construct(private readonly ReviewPayoutAccountService $service) {}

    public function __invoke(ReviewPayoutAccountRequest $request, string $account): RedirectResponse
    {
        $this->service->handle(
            $request->identity(),
            $account,
            PayoutAccountStatus::from($request->validated('decision')),
            $request->validated('reason'),
        );

        return back()->with('status', 'Review rekening payout tersimpan.');
    }
}

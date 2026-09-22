<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\InquirePaymentRequest;
use App\Http\Requests\Payments\PaymentQueryRequest;
use App\Http\Requests\Payments\ToggleChannelRequest;
use App\Services\Payments\GetPaymentReconciliationService;
use App\Services\Payments\InquirePaymentService;
use App\Services\Payments\ManagePaymentChannelsService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class PaymentSupportController extends Controller
{
    public function __construct(
        private readonly InquirePaymentService $inquiry,
        private readonly ManagePaymentChannelsService $channels,
        private readonly GetPaymentReconciliationService $reconciliation,
    ) {}

    public function index(PaymentQueryRequest $request): Response
    {
        return Inertia::render('super-user/payments', $this->reconciliation->handle($request->identity(), $request->filters()));
    }

    public function inquire(InquirePaymentRequest $request, string $payment): RedirectResponse
    {
        $this->inquiry->handle($request->identity(), $payment);

        return back()->with('status', 'Inquiry terkirim. Status diperbarui dari provider.');
    }

    public function toggleChannel(ToggleChannelRequest $request, string $channel): RedirectResponse
    {
        $this->channels->setActive(
            $request->identity(),
            $channel,
            (bool) $request->validated('active'),
            (string) $request->validated('reason'),
        );

        return back()->with('status', 'Allowlist channel diperbarui.');
    }
}

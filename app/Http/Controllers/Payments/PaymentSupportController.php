<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\InquirePaymentRequest;
use App\Http\Requests\Payments\ToggleChannelRequest;
use App\Services\Payments\InquirePaymentService;
use App\Services\Payments\ManagePaymentChannelsService;
use Illuminate\Http\RedirectResponse;

final class PaymentSupportController extends Controller
{
    public function __construct(
        private readonly InquirePaymentService $inquiry,
        private readonly ManagePaymentChannelsService $channels,
    ) {}

    public function inquire(InquirePaymentRequest $request, string $payment): RedirectResponse
    {
        $this->inquiry->handle($request->identity(), $payment);

        return back()->with('status', 'Inquiry terkirim. Status diperbarui dari provider.');
    }

    public function toggleChannel(ToggleChannelRequest $request): RedirectResponse
    {
        $this->channels->setActive($request->identity(), (string) $request->validated('channel_code'), (bool) $request->validated('active'));

        return back()->with('status', 'Allowlist channel diperbarui.');
    }
}

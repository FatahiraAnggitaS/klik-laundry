<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\CreatePaymentInvoiceRequest;
use App\Http\Requests\Payments\ShowPaymentRequest;
use App\Services\Payments\CreatePaymentInvoiceService;
use App\Services\Payments\GetPaymentCheckoutService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class PaymentController extends Controller
{
    public function __construct(
        private readonly CreatePaymentInvoiceService $creator,
        private readonly GetPaymentCheckoutService $checkout,
    ) {}

    public function create(ShowPaymentRequest $request, string $order): Response
    {
        return Inertia::render('payments/create', $this->checkout->forOrder($request->identity(), $order));
    }

    public function store(CreatePaymentInvoiceRequest $request, string $order): RedirectResponse
    {
        $payment = $this->creator->handle($request->identity(), $order, (string) $request->validated('channel_code'));

        return redirect()->route('payments.show', $payment->publicId)->with('status', 'Tagihan dibuat. Selesaikan pembayaran sebelum kedaluwarsa.');
    }

    public function show(ShowPaymentRequest $request, string $payment): Response
    {
        return Inertia::render('payments/show', $this->checkout->forPayment($request->identity(), $payment));
    }

    public function receipt(ShowPaymentRequest $request, string $payment): Response
    {
        return Inertia::render('payments/receipt', $this->checkout->forPayment($request->identity(), $payment));
    }
}

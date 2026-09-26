<?php

namespace App\Http\Controllers\Finance;

use App\Enums\RefundStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\CompleteRefundRequest;
use App\Http\Requests\Finance\FinanceQueryRequest;
use App\Http\Requests\Finance\ReviewRefundRequest;
use App\Http\Requests\Finance\SubmitRefundRequest;
use App\Services\Finance\GetFinanceDashboardService;
use App\Services\Finance\ReviewRefundService;
use App\Services\Finance\SubmitRefundRequestService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class RefundController extends Controller
{
    public function __construct(private readonly SubmitRefundRequestService $submit, private readonly ReviewRefundService $review, private readonly GetFinanceDashboardService $dashboard) {}

    public function store(SubmitRefundRequest $request): RedirectResponse
    {
        $this->submit->handle($request->identity(), (string) $request->validated('payment_public_id'), (string) $request->validated('reason'));

        return back()->with('status', 'Permintaan refund berhasil diajukan.');
    }

    public function show(FinanceQueryRequest $request, string $refund): Response
    {
        return Inertia::render('finance/refund-detail', ['refund' => $this->dashboard->refundDetail($request->identity(), $refund)]);
    }

    public function review(ReviewRefundRequest $request, string $refund): RedirectResponse
    {
        $this->review->review($request->identity(), $refund, RefundStatus::from((string) $request->validated('decision')), (string) $request->validated('reason'));

        return back()->with('status', 'Review refund berhasil disimpan.');
    }

    public function complete(CompleteRefundRequest $request, string $refund): RedirectResponse
    {
        $this->review->complete($request->identity(), $refund, (string) $request->validated('method'), (string) $request->validated('reference'), (string) $request->validated('reason'));

        return back()->with('status', 'Refund manual ditandai selesai.');
    }
}

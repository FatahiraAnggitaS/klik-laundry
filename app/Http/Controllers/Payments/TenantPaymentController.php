<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\ShowPaymentRequest;
use App\Services\Payments\GetTenantPaymentsService;
use Inertia\Inertia;
use Inertia\Response;

final class TenantPaymentController extends Controller
{
    public function __construct(private readonly GetTenantPaymentsService $payments) {}

    public function index(ShowPaymentRequest $request): Response
    {
        return Inertia::render('tenant/payments', $this->payments->handle($request->identity()));
    }
}

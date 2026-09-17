<?php

namespace App\Http\Controllers\Orders;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Orders\CreateOrderRequest;
use App\Http\Requests\Orders\OrderActionRequest;
use App\Http\Requests\Orders\OrderQueryRequest;
use App\Http\Requests\Orders\RescheduleOrderRequest;
use App\Services\Orders\CreateOrderService;
use App\Services\Orders\GetOrdersService;
use App\Services\Orders\ManageOrderLifecycleService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class OrderController extends Controller
{
    public function __construct(
        private readonly CreateOrderService $creator,
        private readonly GetOrdersService $query,
        private readonly ManageOrderLifecycleService $lifecycle,
    ) {}

    public function store(CreateOrderRequest $request): RedirectResponse
    {
        $order = $this->creator->handle($request->identity(), $request->toDto());

        return redirect()->route('orders.show', $order->publicId)->with('status', 'Order berhasil dibuat.');
    }

    public function index(OrderQueryRequest $request): Response
    {
        return Inertia::render('orders/index', $this->query->list($request->identity(), $request->filters()));
    }

    public function show(OrderQueryRequest $request, string $order): Response
    {
        return Inertia::render('orders/show', $this->query->detail($request->identity(), $order));
    }

    public function receipt(OrderQueryRequest $request, string $order): Response
    {
        return Inertia::render('orders/receipt', $this->query->detail($request->identity(), $order, true));
    }

    public function cancel(OrderActionRequest $request, string $order): RedirectResponse
    {
        $this->lifecycle->cancel($request->identity(), $order, $request->validated('reason'));

        return back()->with('status', 'Order berhasil dibatalkan.');
    }

    public function reschedule(RescheduleOrderRequest $request, string $order): RedirectResponse
    {
        $this->lifecycle->reschedulePickup($request->identity(), $order, $request->string('pickup_slot_public_id')->toString(), $request->string('pickup_date')->toString(), $request->validated('reason'));

        return back()->with('status', 'Jadwal pickup berhasil diperbarui.');
    }

    public function tenantIndex(OrderQueryRequest $request): Response
    {
        abort_unless($request->identity()->role() === UserRole::TenantOwner, 403);

        return $this->index($request);
    }

    public function tenantShow(OrderQueryRequest $request, string $order): Response
    {
        abort_unless($request->identity()->role() === UserRole::TenantOwner, 403);

        return $this->show($request, $order);
    }
}

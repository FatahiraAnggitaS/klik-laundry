<?php

namespace App\Http\Controllers\Outlets;

use App\Contracts\IdentityUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Outlets\ShowOutletRequest;
use App\Services\Outlets\GetOutletDetailsService;
use Inertia\Inertia;
use Inertia\Response;

final class ShowOutletController extends Controller
{
    public function __construct(private readonly GetOutletDetailsService $service) {}

    public function __invoke(ShowOutletRequest $request, string $outlet): Response
    {
        $user = $request->user();

        return Inertia::render('outlets/show', $this->service->handle(
            $outlet,
            $user instanceof IdentityUser ? $user : null,
            $request->validated('pickup_address'),
            $request->validated('delivery_address'),
        ));
    }
}

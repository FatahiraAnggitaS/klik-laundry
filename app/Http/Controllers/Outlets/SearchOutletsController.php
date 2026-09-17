<?php

namespace App\Http\Controllers\Outlets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Outlets\SearchOutletsRequest;
use App\Services\Outlets\SearchOutletsService;
use Inertia\Inertia;
use Inertia\Response;

final class SearchOutletsController extends Controller
{
    public function __construct(private readonly SearchOutletsService $service) {}

    public function __invoke(SearchOutletsRequest $request): Response
    {
        return Inertia::render('outlets/index', $this->service->handle(
            query: $request->validated('q'),
            pricingType: $request->validated('pricing_type'),
            latitude: $request->filled('latitude') ? $request->float('latitude') : null,
            longitude: $request->filled('longitude') ? $request->float('longitude') : null,
        ));
    }
}

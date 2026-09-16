<?php

namespace App\Http\Controllers\Foundation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Foundation\ShowPlatformSettingsRequest;
use App\Services\Foundation\GetPlatformSettingsService;
use Inertia\Inertia;
use Inertia\Response;

final class ShowPlatformSettingsController extends Controller
{
    public function __construct(
        private readonly GetPlatformSettingsService $service,
    ) {}

    public function __invoke(ShowPlatformSettingsRequest $request): Response
    {
        return Inertia::render(
            'foundation/platform-settings',
            $this->service->handle()->toArray(),
        );
    }
}

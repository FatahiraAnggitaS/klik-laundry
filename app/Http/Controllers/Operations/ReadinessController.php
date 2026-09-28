<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Services\Operations\CheckReadinessService;
use Illuminate\Http\JsonResponse;

final class ReadinessController extends Controller
{
    public function __construct(private readonly CheckReadinessService $service) {}

    public function __invoke(): JsonResponse
    {
        if ($this->service->handle()) {
            return response()->json(['status' => 'ready']);
        }

        return response()->json(['status' => 'unavailable'], 503);
    }
}

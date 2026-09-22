<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\DuitkuCallbackRequest;
use App\Services\Payments\ProcessDuitkuCallbackService;
use Illuminate\Http\JsonResponse;

final class DuitkuCallbackController extends Controller
{
    public function __construct(private readonly ProcessDuitkuCallbackService $callback) {}

    public function __invoke(DuitkuCallbackRequest $request): JsonResponse
    {
        if ($request->hasValidTransport()) {
            $this->callback->handle($request->callbackPayload());
        }

        return response()->json(['received' => true]);
    }
}

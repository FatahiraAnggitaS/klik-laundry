<?php

namespace App\Http\Controllers\Dispatch;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dispatch\PrivateProofRequest;
use App\Services\Dispatch\GetPrivateProofUrlService;
use Illuminate\Http\RedirectResponse;

final class PrivateProofController extends Controller
{
    public function task(PrivateProofRequest $request, string $task, GetPrivateProofUrlService $service): RedirectResponse
    {
        return redirect()->away($service->task($request->identity(), $task));
    }

    public function weight(PrivateProofRequest $request, string $order, GetPrivateProofUrlService $service): RedirectResponse
    {
        return redirect()->away($service->weight($request->identity(), $order));
    }
}

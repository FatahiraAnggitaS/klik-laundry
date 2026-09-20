<?php

namespace App\Http\Controllers\Dispatch;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dispatch\WeightConfirmationRequest;
use App\Services\Dispatch\ConfirmLaundryWeightService;
use Illuminate\Http\RedirectResponse;

final class WeightConfirmationController extends Controller
{
    public function __invoke(WeightConfirmationRequest $request, string $order, ConfirmLaundryWeightService $service): RedirectResponse
    {
        $service->handle($request->identity(), $order, $request->integer('actual_grams'), $request->string('reason')->toString() ?: null, $request->file('proof'));

        return back()->with('status', 'Berat dan total final berhasil dikonfirmasi.');
    }
}

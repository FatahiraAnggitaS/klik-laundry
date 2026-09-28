<?php

namespace App\Http\Controllers\SuperUser;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperUser\GrantPiiRevealRequest;
use App\Http\Requests\SuperUser\ShowPiiPrivacyRequest;
use App\Services\Privacy\ManagePiiRevealService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

final class PiiPrivacyController extends Controller
{
    public function __construct(private readonly ManagePiiRevealService $service) {}

    public function show(ShowPiiPrivacyRequest $request, string $order): Response
    {
        $result = $this->service->view($request->identity(), $order, $request->session()->getId());
        Inertia::encryptHistory();
        $response = Inertia::render('super-user/privacy/show', $result)->toResponse($request);
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }

    public function store(GrantPiiRevealRequest $request, string $order): RedirectResponse
    {
        $this->service->grant($request->identity(), $order, $request->session()->getId(), (string) $request->validated('reason'));

        return back()->with('status', 'Akses PII diberikan selama 15 menit untuk order ini.');
    }
}

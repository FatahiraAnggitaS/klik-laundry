<?php

namespace App\Http\Controllers\Identity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\ConfirmSensitiveAuthenticationRequest;
use App\Services\Identity\ConfirmSensitiveAuthenticationService;
use Illuminate\Http\RedirectResponse;

final class ConfirmSensitiveAuthenticationController extends Controller
{
    public function __construct(private readonly ConfirmSensitiveAuthenticationService $service) {}

    public function __invoke(ConfirmSensitiveAuthenticationRequest $request): RedirectResponse
    {
        $this->service->handle(
            $request->identity(),
            $request->validated('password'),
            $request->validated('code'),
        );
        $request->session()->put('auth.sensitive_confirmed_at', time());

        return redirect()->intended(route('workspace'));
    }
}

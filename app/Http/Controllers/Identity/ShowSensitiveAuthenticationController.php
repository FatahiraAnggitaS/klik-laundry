<?php

namespace App\Http\Controllers\Identity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\ShowSensitiveAuthenticationRequest;
use Inertia\Inertia;
use Inertia\Response;

final class ShowSensitiveAuthenticationController extends Controller
{
    public function __invoke(ShowSensitiveAuthenticationRequest $request): Response
    {
        $returnTo = $request->validated('return_to');

        if (is_string($returnTo)) {
            $request->session()->put('url.intended', $returnTo);
        }

        return Inertia::render('identity/confirm-sensitive');
    }
}

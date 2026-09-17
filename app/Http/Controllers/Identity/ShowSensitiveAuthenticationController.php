<?php

namespace App\Http\Controllers\Identity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\ShowWorkspaceRequest;
use Inertia\Inertia;
use Inertia\Response;

final class ShowSensitiveAuthenticationController extends Controller
{
    public function __invoke(ShowWorkspaceRequest $request): Response
    {
        return Inertia::render('identity/confirm-sensitive');
    }
}

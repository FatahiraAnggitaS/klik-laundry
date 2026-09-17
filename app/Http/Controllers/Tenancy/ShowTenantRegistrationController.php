<?php

namespace App\Http\Controllers\Tenancy;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

final class ShowTenantRegistrationController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('auth/register-tenant');
    }
}

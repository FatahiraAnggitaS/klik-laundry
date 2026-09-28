<?php

namespace App\Http\Controllers\Identity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\CloseCustomerAccountRequest;
use App\Services\Privacy\CloseCustomerAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

final class CloseCustomerAccountController extends Controller
{
    public function __construct(private readonly CloseCustomerAccountService $service) {}

    public function __invoke(CloseCustomerAccountRequest $request): RedirectResponse
    {
        $this->service->handle($request->identity());
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('status', 'Akun telah ditutup dan profil Anda dianonimkan.');
    }
}

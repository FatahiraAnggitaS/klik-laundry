<?php

namespace App\Http\Controllers\Identity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\UpdateProfileRequest;
use App\Services\Identity\UpdateProfileService;
use Illuminate\Http\RedirectResponse;

final class UpdateProfileController extends Controller
{
    public function __construct(private readonly UpdateProfileService $service) {}

    public function __invoke(UpdateProfileRequest $request): RedirectResponse
    {
        $this->service->handle(
            $request->identity(),
            trim($request->validated('name')),
            trim($request->validated('phone')),
        );

        return back()->with('status', 'Profil berhasil diperbarui.');
    }
}

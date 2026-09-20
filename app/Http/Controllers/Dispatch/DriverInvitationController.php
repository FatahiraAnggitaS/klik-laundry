<?php

namespace App\Http\Controllers\Dispatch;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dispatch\AcceptDriverInvitationRequest;
use App\Http\Requests\Dispatch\ConsumeDriverInvitationRequest;
use App\Services\Dispatch\ManageDriverInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DriverInvitationController extends Controller
{
    public function consume(ConsumeDriverInvitationRequest $request, string $invitation, string $token, ManageDriverInvitationService $service): RedirectResponse
    {
        abort_unless($request->user() === null, 404);
        $data = $service->consume($invitation, $token);
        $request->session()->put('driver.invitation', [
            'publicId' => $data->publicId,
            'tokenHash' => hash('sha256', $token),
            'tenantName' => $data->tenantName,
            'emailMasked' => $this->maskEmail($data->email),
            'expiresAt' => $data->expiresAt,
        ]);

        return to_route('driver.invitation.accept.create');
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user() === null && is_array($request->session()->get('driver.invitation')), 404);
        $grant = $request->session()->get('driver.invitation');

        return Inertia::render('auth/accept-driver-invitation', [
            'invitation' => ['tenantName' => $grant['tenantName'], 'emailMasked' => $grant['emailMasked'], 'expiresAt' => $grant['expiresAt']],
        ]);
    }

    public function store(AcceptDriverInvitationRequest $request, ManageDriverInvitationService $service): RedirectResponse
    {
        $grant = $request->session()->pull('driver.invitation');
        $service->accept($grant['publicId'], $grant['tokenHash'], $request->string('name')->toString(), $request->string('password')->toString());

        return to_route('login')->with('status', 'Akun Driver aktif. Silakan login dan atur availability.');
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = explode('@', $email, 2);

        return mb_substr($local, 0, 1).'***@'.$domain;
    }
}

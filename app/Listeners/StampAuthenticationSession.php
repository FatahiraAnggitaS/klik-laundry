<?php

namespace App\Listeners;

use App\Contracts\IdentityUser;
use Illuminate\Auth\Events\Login;
use Illuminate\Session\Store;

final readonly class StampAuthenticationSession
{
    public function __construct(private Store $session) {}

    public function handle(Login $event): void
    {
        if ($event->user instanceof IdentityUser) {
            $this->session->put('auth.version', $event->user->authVersion());
            $this->session->forget('auth.sensitive_confirmed_at');
        }
    }
}

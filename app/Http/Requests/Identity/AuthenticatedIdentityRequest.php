<?php

namespace App\Http\Requests\Identity;

use App\Contracts\IdentityUser;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

abstract class AuthenticatedIdentityRequest extends FormRequest
{
    public function identity(): IdentityUser
    {
        $user = $this->user();

        if (! $user instanceof IdentityUser) {
            throw new AccessDeniedHttpException;
        }

        return $user;
    }
}

<?php

namespace App\Http\Requests\Notifications;

use App\Http\Requests\Identity\AuthenticatedIdentityRequest;

final class NotificationRequest extends AuthenticatedIdentityRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }
}

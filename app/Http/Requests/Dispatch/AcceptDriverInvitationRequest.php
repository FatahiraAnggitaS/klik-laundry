<?php

namespace App\Http\Requests\Dispatch;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class AcceptDriverInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() === null && is_array($this->session()->get('driver.invitation'));
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', Password::min(12)->mixedCase()->numbers(), 'confirmed'],
        ];
    }
}

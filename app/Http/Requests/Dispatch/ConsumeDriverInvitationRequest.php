<?php

namespace App\Http\Requests\Dispatch;

use Illuminate\Foundation\Http\FormRequest;

final class ConsumeDriverInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() === null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [];
    }
}

<?php

namespace App\Http\Requests\Payments;

use Illuminate\Foundation\Http\FormRequest;

final class DuitkuCallbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'merchantCode' => ['required', 'string', 'max:32'],
            'amount' => ['required', 'integer', 'min:1'],
            'merchantOrderId' => ['required', 'string', 'max:64'],
            'reference' => ['required', 'string', 'max:64'],
            'signature' => ['required', 'string', 'max:128'],
            'resultCode' => ['required', 'string', 'max:8'],
        ];
    }

    /** @return array<string, mixed> */
    public function callbackPayload(): array
    {
        /** @var array<string, mixed> $payload */
        $payload = $this->only(['merchantCode', 'amount', 'merchantOrderId', 'reference', 'signature', 'resultCode']);

        return $payload;
    }
}

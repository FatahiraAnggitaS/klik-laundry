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
        return [];
    }

    public function hasValidTransport(): bool
    {
        $contentType = strtolower((string) $this->header('Content-Type'));
        $contentLength = $this->header('Content-Length');

        return str_starts_with($contentType, 'application/x-www-form-urlencoded')
            && (! is_numeric($contentLength) || (int) $contentLength <= 16_384);
    }

    /** @return array<string, mixed> */
    public function callbackPayload(): array
    {
        /** @var array<string, mixed> $payload */
        $payload = $this->only(['merchantCode', 'amount', 'merchantOrderId', 'reference', 'signature', 'resultCode']);

        return $payload;
    }
}

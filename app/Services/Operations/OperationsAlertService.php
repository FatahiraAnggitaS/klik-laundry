<?php

namespace App\Services\Operations;

use App\Notifications\OperationsAlertNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

final class OperationsAlertService
{
    /** @param array<string, bool|int|string|null> $context */
    public function send(string $code, string $title, string $message, array $context = []): void
    {
        Log::warning('operations.alert', ['code' => $code, ...$context]);

        $email = config('operations.alert_email');
        if (! is_string($email) || $email === '') {
            return;
        }

        try {
            RateLimiter::attempt(
                "operations-alert:{$code}",
                1,
                fn () => Notification::route('mail', $email)->notifyNow(new OperationsAlertNotification($title, $message)),
                (int) config('operations.alert_cooldown_seconds', 900),
            );
        } catch (Throwable $exception) {
            Log::error('operations.alert_delivery_failed', ['code' => $code, 'error_class' => class_basename($exception)]);
        }
    }
}

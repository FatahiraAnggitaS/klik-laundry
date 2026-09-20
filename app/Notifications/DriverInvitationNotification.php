<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class DriverInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $tenantName,
        private readonly string $acceptUrl,
        private readonly string $expiresAt,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Undangan Driver Klik Laundry')
            ->greeting('Halo,')
            ->line("Anda diundang menjadi Driver untuk {$this->tenantName}.")
            ->action('Terima undangan', $this->acceptUrl)
            ->line("Undangan berlaku sampai {$this->expiresAt} dan hanya dapat digunakan sekali.");
    }
}

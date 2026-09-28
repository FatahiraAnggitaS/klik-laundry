<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class OperationsAlertNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $title, private readonly string $summary) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('[Klik Laundry] '.$this->title)->line($this->summary);
    }
}

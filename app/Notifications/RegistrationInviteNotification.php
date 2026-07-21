<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RegistrationInviteNotification extends Notification
{
    public function __construct(
        private readonly string $username,
        private readonly string $inviteUrl,
    ) {
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('You have been invited to '.config('app.name'))
            ->greeting('You have been invited!')
            ->line("An account with the username @{$this->username} has been reserved for you.")
            ->line('Use the button below to create your account. This invitation can only be used once.')
            ->action('Accept invitation', $this->inviteUrl)
            ->line('If you were not expecting this invitation, you can ignore this email.');
    }
}

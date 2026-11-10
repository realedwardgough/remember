<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerifyEmailChange extends Notification
{
    public function __construct(public readonly string $url)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Confirm your Remember email address')
            ->line('Confirm this email address to use it for your Remember account.')
            ->action('Confirm email address', $this->url)
            ->line('This link expires in 30 minutes. Sign in to the account that requested this change. If you did not request it, ignore this email.');
    }
}

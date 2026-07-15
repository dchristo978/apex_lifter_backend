<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Emails a short numeric code the lifter types back into the app to reset their
 * password. The mobile app has no web views or deep-link handling, so a
 * copy-a-code flow is used instead of Fortify's link-based reset.
 */
class ResetPasswordCode extends Notification
{
    use Queueable;

    public function __construct(public readonly string $code) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Reset your Apex Lifter password')
            ->greeting('Password reset')
            ->line('Enter this code in the app to set a new password:')
            ->line("**{$this->code}**")
            ->line('The code expires in 60 minutes.')
            ->line('If you did not request this, you can ignore this email.');
    }
}

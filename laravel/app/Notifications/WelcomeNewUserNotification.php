<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when an administrator creates a new ForgeDesk account.
 *
 * Delivers the one-time temporary password and tells the user they must sign in
 * and choose a new password within the configured window (7 days by default).
 */
class WelcomeNewUserNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $temporaryPassword) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $ttlHours = (int) config('auth.temp_password.ttl_hours', 168);
        $window = $ttlHours % 24 === 0
            ? (($ttlHours / 24).' '.($ttlHours === 24 ? 'day' : 'days'))
            : ($ttlHours.' '.($ttlHours === 1 ? 'hour' : 'hours'));
        $loginUrl = rtrim(config('app.url', 'http://localhost'), '/').'/login';

        return (new MailMessage)
            ->subject('Welcome to ForgeDesk — your account is ready')
            ->greeting('Hello '.($notifiable->first_name ?: $notifiable->name).'!')
            ->line('An administrator has created a ForgeDesk account for you. Use the temporary credentials below to sign in.')
            ->line('**Email:** '.$notifiable->email)
            ->line('**Temporary password:** '.$this->temporaryPassword)
            ->action('Log in to ForgeDesk', $loginUrl)
            ->line("For security, you must sign in and set a new password within {$window}. After that the temporary password stops working and an administrator will need to resend your invitation.")
            ->line('If you were not expecting this email, please contact your ForgeDesk administrator.')
            ->salutation('Regards, The ForgeDesk Team');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'email' => $notifiable->email,
        ];
    }
}

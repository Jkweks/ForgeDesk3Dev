<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The password reset token.
     *
     * @var string
     */
    public $token;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $token)
    {
        $this->token = $token;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $resetUrl = $this->getResetUrl($notifiable);
        $expiryMinutes = config('auth.passwords.users.expire', 60);

        return (new MailMessage)
            ->subject('Reset Your ForgeDesk Password')
            ->greeting('Hello ' . ($notifiable->first_name ?: $notifiable->name) . '!')
            ->line('You are receiving this email because we received a password reset request for your account.')
            ->action('Reset Password', $resetUrl)
            ->line("This password reset link will expire in {$expiryMinutes} minutes.")
            ->line('If you did not request a password reset, no further action is required.')
            ->salutation('Regards, The ForgeDesk Team');
    }

    /**
     * Get the password reset URL.
     */
    protected function getResetUrl(object $notifiable): string
    {
        $appUrl = config('app.url', 'http://localhost:8000');

        // Build reset URL with token and email
        return $appUrl . '/password/reset?' . http_build_query([
            'token' => $this->token,
            'email' => $notifiable->email,
        ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'token' => $this->token,
            'email' => $notifiable->email,
        ];
    }
}

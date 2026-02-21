<?php

namespace App\Notifications;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class TenantActivationEmail extends Notification
{
    use Queueable;

    public function __construct(
        private User $user,
        private Tenant $tenant,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = URL::temporarySignedRoute(
            'onboarding.show',
            now()->addDays(7),
            ['user' => $this->user->id],
        );

        return (new MailMessage)
            ->subject('Welcome to '.config('app.name').' — Complete Your Setup')
            ->greeting('Welcome!')
            ->line('Your subscription has been created successfully. Please complete your account setup to get started.')
            ->action('Complete Setup', $url)
            ->line('This link will expire in 7 days.');
    }
}

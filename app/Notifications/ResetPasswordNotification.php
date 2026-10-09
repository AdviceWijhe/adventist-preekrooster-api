<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Services\Branding\BrandingService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Password;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $token
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');
        $email = urlencode((string) $notifiable->getEmailForPasswordReset());
        $url = "{$frontendUrl}/wachtwoord-reset/{$this->token}?email={$email}";

        if (method_exists(Password::broker(), 'getRepository') &&
            method_exists(Password::broker()->getRepository(), 'getExpires')) {
            $expire = Password::broker()->getRepository()->getExpires();
        } else {
            $expire = config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);
        }

        $branding = app(BrandingService::class)->mailBranding();

        return (new MailMessage)
            ->subject('Wachtwoord reset aanvragen')
            ->view(['mail.reset_password_html', 'mail.reset_password'], [
                'resetUrl' => $url,
                'expireMinutes' => $expire,
                'branding' => $branding,
            ]);
    }
}

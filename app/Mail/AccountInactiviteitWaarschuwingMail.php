<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Concerns\InteractsWithBranding;
use App\Mail\Concerns\InteractsWithMailTemplate;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountInactiviteitWaarschuwingMail extends Mailable
{
    use InteractsWithBranding;
    use InteractsWithMailTemplate;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly int $dagenTotDeactivatie
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->tekst()['onderwerp'],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.account_inactiviteit_waarschuwing_html',
            text: 'mail.account_inactiviteit_waarschuwing',
            with: [
                'user' => $this->user,
                'dagenTotDeactivatie' => $this->dagenTotDeactivatie,
                'branding' => $this->branding(),
                'inhoud' => $this->tekst()['inhoud'],
            ]
        );
    }

    /**
     * @return array{onderwerp: string, inhoud: string}
     */
    private function tekst(): array
    {
        return $this->mailTemplate('account_inactiviteit_waarschuwing', [
            'voornaam' => $this->user->voornaam,
            'dagen' => (string) $this->dagenTotDeactivatie,
        ]);
    }
}

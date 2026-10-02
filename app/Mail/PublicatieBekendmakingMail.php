<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Concerns\InteractsWithBranding;
use App\Mail\Concerns\InteractsWithMailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PublicatieBekendmakingMail extends Mailable
{
    use InteractsWithBranding;
    use InteractsWithMailTemplate;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $periode
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
            view: 'mail.publicatie_bekendmaking_html',
            text: 'mail.publicatie_bekendmaking',
            with: [
                'periode' => $this->periode,
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
        return $this->mailTemplate('publicatie_bekendmaking', ['periode' => $this->periode]);
    }
}

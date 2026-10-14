<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Concerns\InteractsWithBranding;
use App\Mail\Concerns\InteractsWithMailTemplate;
use App\Models\Spreekbeurt;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SpreekbeurtAutoAnnuleringMail extends Mailable
{
    use InteractsWithBranding;
    use InteractsWithMailTemplate;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly Spreekbeurt $spreekbeurt) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->tekst()['onderwerp'],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.spreekbeurt_auto_annulering_html',
            text: 'mail.spreekbeurt_auto_annulering',
            with: [
                'spreekbeurt' => $this->spreekbeurt,
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
        return $this->mailTemplate('spreekbeurt_auto_annulering');
    }
}

<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Concerns\InteractsWithBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TwoFactorCodeMail extends Mailable
{
    use InteractsWithBranding;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $code,
        public readonly string $voornaam
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Je verificatiecode',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.two_factor_code_html',
            text: 'mail.two_factor_code',
            with: [
                'code' => $this->code,
                'voornaam' => $this->voornaam,
                'branding' => $this->branding(),
            ]
        );
    }
}

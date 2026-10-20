<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Concerns\InteractsWithBranding;
use App\Models\Bericht;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BerichtNotificatieMail extends Mailable
{
    use InteractsWithBranding;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Bericht $bericht,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->bericht->titel,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.bericht_notificatie_html',
            text: 'mail.bericht_notificatie',
            with: [
                'bericht' => $this->bericht,
                'branding' => $this->branding(),
            ]
        );
    }
}

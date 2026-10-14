<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Concerns\InteractsWithBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TestMailDiagnostics extends Mailable
{
    use InteractsWithBranding;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $appEnv
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Preekrooster testmail',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.test_diagnostics_html',
            text: 'mail.test_diagnostics',
            with: [
                'appEnv' => $this->appEnv,
                'timestamp' => now()->toDateTimeString(),
                'branding' => $this->branding(),
            ]
        );
    }
}

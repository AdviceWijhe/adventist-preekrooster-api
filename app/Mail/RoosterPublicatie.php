<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Concerns\InteractsWithBranding;
use App\Mail\Concerns\InteractsWithMailTemplate;
use App\Models\Gemeente;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class RoosterPublicatie extends Mailable
{
    use InteractsWithBranding;
    use InteractsWithMailTemplate;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $periode,
        public readonly Collection $diensten,
        public readonly ?Gemeente $gemeente = null
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
            view: 'mail.rooster_publicatie_html',
            text: 'mail.rooster_publicatie',
            with: [
                'periode' => $this->periode,
                'diensten' => $this->diensten,
                'gemeente' => $this->gemeente,
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
        return $this->mailTemplate('rooster_publicatie', [
            'maand' => Carbon::parse($this->periode.'-01')->isoFormat('MMMM YYYY'),
            'gemeente' => $this->gemeente?->naam ?? 'deze periode',
        ]);
    }
}

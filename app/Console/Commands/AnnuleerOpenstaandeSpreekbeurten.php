<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\SpreekbeurtAutoAnnuleringMail;
use App\Models\Spreekbeurt;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class AnnuleerOpenstaandeSpreekbeurten extends Command
{
    protected $signature = 'rooster:annuleer-openstaand {--dagen= : Aantal dagen vooraf (overschrijft config)}';

    protected $description = 'Verwijdert onbevestigde preekbeurten die binnen X dagen plaatsvinden';

    public function handle(): int
    {
        if (! config('rooster.auto_annuleer.enabled', false)) {
            $this->warn('Auto-annulering is uitgeschakeld (ROOSTER_AUTO_ANNULEER_ENABLED=false).');

            return self::SUCCESS;
        }

        $dagen = (int) ($this->option('dagen') ?: config('rooster.auto_annuleer.dagen_vooraf', 7));
        $start = today()->toDateString();
        $eind = today()->addDays($dagen)->toDateString();

        $openstaand = Spreekbeurt::query()
            ->with(['dienst.gemeente.contactpersoon', 'spreker'])
            ->whereNull('bevestigd')
            ->whereHas('dienst', fn ($query) => $query->whereBetween('datum', [$start, $eind]))
            ->get();

        $verwijderd = 0;

        foreach ($openstaand as $spreekbeurt) {
            if (config('mail.features.spreekbeurt_auto_annulering', true)) {
                $this->stuurMeldingen($spreekbeurt);
            }

            $spreekbeurt->delete();
            $verwijderd++;
        }

        $this->info("{$verwijderd} openstaande preekbeurt(en) geannuleerd (venster {$start} t/m {$eind}).");

        return self::SUCCESS;
    }

    private function stuurMeldingen(Spreekbeurt $spreekbeurt): void
    {
        $emails = [];

        if (filled($spreekbeurt->spreker?->email)) {
            $emails[] = $spreekbeurt->spreker->email;
        }

        $contactEmail = $spreekbeurt->dienst?->gemeente?->contactpersoon?->email;
        if (filled($contactEmail)) {
            $emails[] = $contactEmail;
        }

        $emails = array_values(array_unique(array_filter($emails)));

        foreach ($emails as $email) {
            Mail::to($email)->send(new SpreekbeurtAutoAnnuleringMail($spreekbeurt));
        }
    }
}

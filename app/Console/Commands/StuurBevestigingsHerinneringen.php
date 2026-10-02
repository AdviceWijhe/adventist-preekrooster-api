<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\BevestigingsHerinnering;
use App\Models\Spreekbeurt;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class StuurBevestigingsHerinneringen extends Command
{
    protected $signature = 'rooster:herinneringen {--weken=2 : Stuur herinneringen voor beurten binnen X weken}';

    protected $description = 'Stuurt herinneringsmails voor onbevestigde preekbeurten';

    public function handle(): int
    {
        $weken = (int) $this->option('weken');
        $deadline = now()->addWeeks($weken)->toDateString();
        $vandaag = today()->toDateString();

        $onbevestigd = Spreekbeurt::query()
            ->with(['dienst.gemeente', 'spreker.roles'])
            ->whereNull('bevestigd')
            ->whereHas('dienst', function ($query) use ($vandaag, $deadline): void {
                $query->whereBetween('datum', [$vandaag, $deadline]);
            })
            ->get()
            ->groupBy('spreker_id');

        $verstuurd = 0;

        foreach ($onbevestigd as $beurten) {
            $predikant = $beurten->first()?->spreker;

            if (! $predikant || ! $predikant->active || ! $predikant->email) {
                continue;
            }

            if (! config('mail.features.bevestigings_herinnering', true)) {
                continue;
            }

            Mail::to($predikant->email)->send(new BevestigingsHerinnering($predikant, $beurten));
            $verstuurd++;
        }

        $this->info("Herinneringen verstuurd naar {$verstuurd} predikant(en).");

        return self::SUCCESS;
    }
}

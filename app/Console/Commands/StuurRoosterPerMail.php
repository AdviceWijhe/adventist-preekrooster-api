<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\RoosterPublicatie;
use App\Models\Dienst;
use App\Models\Gemeente;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class StuurRoosterPerMail extends Command
{
    protected $signature = 'rooster:mail-versturen {periode : YYYY-MM formaat}';

    protected $description = 'Verstuurt het gepubliceerde rooster per e-mail naar contactpersonen';

    public function handle(): int
    {
        $periode = (string) $this->argument('periode');

        if (! preg_match('/^\d{4}-\d{2}$/', $periode)) {
            $this->error('Ongeldige periode. Gebruik YYYY-MM formaat.');

            return self::FAILURE;
        }

        $van = $periode.'-01';
        $tot = date('Y-m-t', strtotime($van));

        $diensten = Dienst::query()
            ->with(['gemeente', 'spreekbeurten.spreker', 'bijzonderheid'])
            ->whereBetween('datum', [$van, $tot])
            ->orderBy('datum')
            ->orderBy('gemeente_id')
            ->get();

        $contactpersonen = User::query()
            ->with(['roles'])
            ->where('active', true)
            ->whereHas('roles', function ($query): void {
                $query->where('slug', 'contactpersoon')
                    ->whereNotNull('user_roles.gemeente_id')
                    ->whereIn('user_roles.gemeente_id', Gemeente::query()->where('active', true)->select('id'));
            })
            ->get();

        $actieveGemeentes = Gemeente::query()
            ->where('active', true)
            ->get()
            ->keyBy('id');

        $verstuurd = 0;

        foreach ($contactpersonen as $contactpersoon) {
            if (! $contactpersoon->email) {
                continue;
            }

            $gemeenteId = $contactpersoon->roles
                ->firstWhere('slug', 'contactpersoon')
                ?->pivot
                ?->gemeente_id;

            $gemeente = $gemeenteId ? $actieveGemeentes->get((int) $gemeenteId) : null;

            if (! $gemeente) {
                continue;
            }

            $dienstenVoorOntvanger = $gemeenteId
                ? $diensten->where('gemeente_id', (int) $gemeenteId)->values()
                : $diensten;

            Mail::to($contactpersoon->email)->send(
                new RoosterPublicatie(
                    periode: $periode,
                    diensten: $dienstenVoorOntvanger,
                    gemeente: $gemeente
                )
            );

            $verstuurd++;
        }

        $this->info("Rooster voor {$periode} verstuurd naar {$verstuurd} contactpersoon/personen.");

        return self::SUCCESS;
    }
}

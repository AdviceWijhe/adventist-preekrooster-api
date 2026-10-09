<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Rooster\RoosterPublicatieService;
use Illuminate\Console\Command;

class PubliceerAutomatischRooster extends Command
{
    protected $signature = 'rooster:publiceer-automatisch';

    protected $description = 'Publiceert de huidige maand, en vanaf de 10e ook de volgende maand';

    public function handle(RoosterPublicatieService $publicatieService): int
    {
        if (! config('rooster.automatische_publicatie.enabled', true)) {
            $this->warn('Automatische publicatie is uitgeschakeld (ROOSTER_AUTO_PUBLICATIE_ENABLED=false).');

            return self::SUCCESS;
        }

        $stuurMail = (bool) config('rooster.automatische_publicatie.stuur_bekendmaking', false);
        $periodes = $publicatieService->publiceerPubliekVenster(stuurBekendmaking: $stuurMail);

        $this->info('Gepubliceerd: '.implode(', ', $periodes).'.');

        return self::SUCCESS;
    }
}

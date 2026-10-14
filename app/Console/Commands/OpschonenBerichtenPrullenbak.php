<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Bericht\BerichtService;
use Illuminate\Console\Command;

class OpschonenBerichtenPrullenbak extends Command
{
    protected $signature = 'berichten:opschonen-prullenbak {--dagen=30 : Aantal dagen voordat verwijderde berichten permanent worden opgeschoond}';

    protected $description = 'Verwijdert berichten uit gebruikers-prullenbakken die ouder zijn dan het opgegeven aantal dagen';

    public function handle(BerichtService $berichtService): int
    {
        $dagen = max(1, (int) $this->option('dagen'));
        $verwijderd = $berichtService->opschonenPrullenbak($dagen);

        $this->info(sprintf(
            'Prullenbak opgeschoond: %d vermelding(en) ouder dan %d dagen verwijderd.',
            $verwijderd,
            $dagen,
        ));

        return self::SUCCESS;
    }
}

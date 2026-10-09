<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ChangeLog;
use App\Services\Instellingen\InstellingenService;
use Illuminate\Console\Command;

class OpschonenChangeLog extends Command
{
    protected $signature = 'changelog:opschonen {--dagen= : Aantal dagen voordat changelog-vermeldingen worden opgeschoond (standaard: instelling in beheer)}';

    protected $description = 'Verwijdert changelog-vermeldingen die ouder zijn dan de ingestelde bewaartermijn';

    public function handle(InstellingenService $instellingenService): int
    {
        $optie = $this->option('dagen');
        $dagen = $optie !== null ? max(1, (int) $optie) : $instellingenService->changelog()['bewaartermijn_dagen'];

        $verwijderd = ChangeLog::query()
            ->where('created_at', '<', now()->subDays($dagen))
            ->delete();

        $this->info(sprintf(
            'Changelog opgeschoond: %d vermelding(en) ouder dan %d dag(en) verwijderd.',
            $verwijderd,
            $dagen,
        ));

        return self::SUCCESS;
    }
}

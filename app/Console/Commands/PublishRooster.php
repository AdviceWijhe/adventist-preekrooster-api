<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Rooster\RoosterPublicatieService;
use Illuminate\Console\Command;

class PublishRooster extends Command
{
    protected $signature = 'rooster:publiceer {periode? : YYYY-MM formaat (standaard: huidige maand)}';

    protected $description = 'Publiceert het preekrooster voor de opgegeven maand';

    public function handle(RoosterPublicatieService $publicatieService): int
    {
        $periode = $this->argument('periode') ?? now()->format('Y-m');

        if (! preg_match('/^\d{4}-\d{2}$/', $periode)) {
            $this->error("Ongeldige periode: {$periode}. Gebruik YYYY-MM formaat.");

            return self::FAILURE;
        }

        $publicatieService->publiceerMaand($periode);

        $this->info("Rooster voor {$periode} succesvol gepubliceerd.");

        return self::SUCCESS;
    }
}

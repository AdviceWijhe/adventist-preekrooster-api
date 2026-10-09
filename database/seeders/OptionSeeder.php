<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Option;
use App\Services\Instellingen\InstellingenService;
use Illuminate\Database\Seeder;

class OptionSeeder extends Seeder
{
    public function run(): void
    {
        Option::query()->firstOrCreate(
            ['naam' => 'rooster_vergrendeld'],
            ['waarde' => '0']
        );

        foreach ([
            InstellingenService::KEY_BEURT_ANNULEREN_ENABLED => '1',
            InstellingenService::KEY_BEURT_ANNULEREN_DAGEN => '7',
            InstellingenService::KEY_INACTIVITEIT_ENABLED => '0',
            InstellingenService::KEY_INACTIVITEIT_DAGEN => '365',
            InstellingenService::KEY_INACTIVITEIT_WAARSCHUWING_DAGEN => '14',
            InstellingenService::KEY_AVG_ENABLED => '0',
            InstellingenService::KEY_AVG_DAGEN => '14',
            InstellingenService::KEY_AVG_TITEL => InstellingenService::DEFAULT_AVG_TITEL,
            InstellingenService::KEY_AVG_TITEL_EN => InstellingenService::DEFAULT_AVG_TITEL_EN,
            InstellingenService::KEY_AVG_TEKST_AKKOORD => InstellingenService::DEFAULT_AVG_TEKST_AKKOORD,
            InstellingenService::KEY_AVG_TEKST_AKKOORD_EN => InstellingenService::DEFAULT_AVG_TEKST_AKKOORD_EN,
            InstellingenService::KEY_AVG_TEKST_WEIGERING => InstellingenService::DEFAULT_AVG_TEKST_WEIGERING,
            InstellingenService::KEY_AVG_TEKST_WEIGERING_EN => InstellingenService::DEFAULT_AVG_TEKST_WEIGERING_EN,
        ] as $naam => $waarde) {
            Option::query()->firstOrCreate(['naam' => $naam], ['waarde' => $waarde]);
        }
    }
}

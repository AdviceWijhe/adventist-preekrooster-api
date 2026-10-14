<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Bijzonderheid;
use Illuminate\Database\Seeder;

class BijzonderheidSeeder extends Seeder
{
    public function run(): void
    {
        $bijzonderheden = [
            'Opdragingsdienst',
            'Intrededienst',
            'Doopdienst',
            'Heilig Avondmaalsdienst',
            'Jeugddienst',
            'Middagdienst',
            'Studiemiddag',
        ];

        foreach ($bijzonderheden as $naam) {
            Bijzonderheid::query()->firstOrCreate(['naam' => $naam]);
        }
    }
}

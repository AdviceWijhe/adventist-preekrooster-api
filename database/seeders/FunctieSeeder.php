<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FunctieSeeder extends Seeder
{
    public function run(): void
    {
        $functies = [
            ['naam' => 'Contactpersoon', 'slug' => 'contactpersoon'],
            ['naam' => 'Spreker', 'slug' => 'spreker'],
            ['naam' => 'Predikant', 'slug' => 'predikant'],
        ];

        foreach ($functies as $functie) {
            DB::table('functies')->updateOrInsert(
                ['slug' => $functie['slug']],
                ['naam' => $functie['naam']]
            );
        }
    }
}

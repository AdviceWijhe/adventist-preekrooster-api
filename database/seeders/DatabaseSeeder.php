<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Basisgegevens die in elke omgeving nodig zijn.
        $this->call([
            RoleSeeder::class,
            FunctieSeeder::class,
            TaalSeeder::class,
            BijzonderheidSeeder::class,
            OptionSeeder::class,
            PublicNavigationSeeder::class,
        ]);

        // Realistische testdata (echte kerken, fictieve personen) alleen buiten productie.
        if (app()->environment(['local', 'staging', 'testing'])) {
            $this->call(RealisticTestDataSeeder::class);
        }
    }
}

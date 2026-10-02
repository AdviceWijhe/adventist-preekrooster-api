<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['naam' => 'Administrator', 'slug' => 'admin'],
            ['naam' => 'Beheerder', 'slug' => 'beheerder'],
            ['naam' => 'Gebruiker', 'slug' => 'gebruiker'],
            // Legacy toegangsrollen — worden in Fase 1b uitgefaseerd ten gunste
            // van functies. Voorlopig behouden zodat bestaande routes werken.
            ['naam' => 'Predikant', 'slug' => 'predikant'],
            ['naam' => 'Contactpersoon', 'slug' => 'contactpersoon'],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                ['slug' => $role['slug']],
                ['naam' => $role['naam']]
            );
        }
    }
}

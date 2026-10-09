<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Services\Migratie\OudeDataMapper;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TaalSeeder extends Seeder
{
    public function run(): void
    {
        $talen = (new OudeDataMapper)->talen();

        foreach ($talen as $taal) {
            DB::table('talen')->updateOrInsert(
                ['slug' => $taal['slug']],
                ['naam' => $taal['naam']]
            );
        }
    }
}

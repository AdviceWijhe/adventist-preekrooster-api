<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Bijzonderheid;
use App\Support\BijzonderheidCatalog;
use Illuminate\Database\Seeder;

class BijzonderheidSeeder extends Seeder
{
    public function run(): void
    {
        foreach (BijzonderheidCatalog::NAMEN as $naam) {
            Bijzonderheid::query()->firstOrCreate(['naam' => $naam]);
        }
    }
}

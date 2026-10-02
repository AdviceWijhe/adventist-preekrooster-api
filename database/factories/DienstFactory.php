<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Dienst;
use App\Models\Gemeente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dienst>
 */
class DienstFactory extends Factory
{
    protected $model = Dienst::class;

    public function definition(): array
    {
        return [
            'datum' => fake()->dateTimeBetween('2026-01-01', '2026-12-31')->format('Y-m-d'),
            'gemeente_id' => Gemeente::factory(),
            'type' => fake()->randomElement(['sabbatschool', 'eredienst', 'speciaal']),
            'eigeninvulling' => null,
            'bijzonderheid_id' => null,
            'taal' => 'nl',
            'dienstwijze' => 'fysiek',
        ];
    }
}

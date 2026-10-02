<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Gemeente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Gemeente>
 */
class GemeenteFactory extends Factory
{
    protected $model = Gemeente::class;

    public function definition(): array
    {
        return [
            'naam' => fake()->city().' '.fake()->randomElement(['Noord', 'Zuid', 'Oost', 'West']),
            'naam_kort' => fake()->word(),
            'adres' => fake()->streetAddress(),
            'plaats' => fake()->city(),
            'postcode' => fake()->postcode(),
            'taal' => 'nl',
            'active' => true,
            'church_plant' => false,
            'volgorde' => fake()->numberBetween(1, 100),
        ];
    }
}

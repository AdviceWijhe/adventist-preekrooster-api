<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\UserBeschikbaarheid;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserBeschikbaarheid>
 */
class UserBeschikbaarheidFactory extends Factory
{
    protected $model = UserBeschikbaarheid::class;

    public function definition(): array
    {
        $van = fake()->dateTimeBetween('now', '+3 months');

        return [
            'user_id' => User::factory(),
            'datum_van' => $van->format('Y-m-d'),
            'datum_tot' => (clone $van)->modify('+7 days')->format('Y-m-d'),
            'opmerking' => fake()->optional()->sentence(),
        ];
    }
}

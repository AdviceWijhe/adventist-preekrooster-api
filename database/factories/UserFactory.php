<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'voornaam' => fake()->firstName(),
            'tussenvoegsel' => null,
            'achternaam' => fake()->lastName(),
            'initialen' => strtoupper(fake()->lexify('??')),
            'geslacht' => fake()->randomElement(['m', 'v', 'o']),
            'email' => fake()->unique()->userName().'@example.test',
            'password' => static::$password ??= Hash::make('password'),
            'taal' => 'nl',
            'gemeente_id' => null,
            'functie' => null,
            'photo' => null,
            'telefoonnummer' => null,
            'mobiel' => null,
            'two_factor_enabled' => true,
            'active' => true,
            'remember_token' => Str::random(10),
        ];
    }
}

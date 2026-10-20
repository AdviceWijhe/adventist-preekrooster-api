<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Dienst;
use App\Models\Spreekbeurt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Spreekbeurt>
 */
class SpreekbeurtFactory extends Factory
{
    protected $model = Spreekbeurt::class;

    public function definition(): array
    {
        return [
            'dienst_id' => Dienst::factory(),
            'spreker_id' => User::factory(),
            'bevestigd' => null,
            'bericht' => null,
            'kilometers' => null,
            'ingevoerd_door' => null,
        ];
    }
}

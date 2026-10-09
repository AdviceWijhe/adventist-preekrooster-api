<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\TwoFactorCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TwoFactorOpschonenTest extends TestCase
{
    use RefreshDatabase;

    public function test_opschonen_verwijdert_verlopen_en_oude_gebruikte_codes(): void
    {
        $user = User::factory()->create();

        TwoFactorCode::query()->create([
            'user_id' => $user->id,
            'code' => Hash::make('111111'),
            'used' => false,
            'expires_at' => now()->subHour(),
        ]);
        $used = TwoFactorCode::query()->create([
            'user_id' => $user->id,
            'code' => Hash::make('222222'),
            'used' => true,
            'expires_at' => now()->addMinutes(5),
        ]);
        $used->forceFill(['updated_at' => now()->subDays(2)])->save();

        $keep = TwoFactorCode::query()->create([
            'user_id' => $user->id,
            'code' => Hash::make('333333'),
            'used' => false,
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->artisan('two-factor:opschonen')->assertSuccessful();

        $this->assertDatabaseMissing('two_factor_codes', ['id' => $used->id]);
        $this->assertDatabaseHas('two_factor_codes', ['id' => $keep->id]);
        $this->assertSame(1, TwoFactorCode::query()->count());
    }
}

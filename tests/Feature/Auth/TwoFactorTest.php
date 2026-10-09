<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Mail\TwoFactorCodeMail;
use App\Models\TwoFactorCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    public function test_gebruiker_krijgt_toegang_met_correcte_code(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        TwoFactorCode::query()->create([
            'user_id' => $user->id,
            'code' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/auth/two-factor/challenge', [
            'code' => '123456',
        ]);

        $response->assertStatus(200)->assertJson(['verified' => true]);
    }

    public function test_code_met_voorloopnul_wordt_geaccepteerd_zonder_die_nul(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        TwoFactorCode::query()->create([
            'user_id' => $user->id,
            'code' => Hash::make('012345'),
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/auth/two-factor/challenge', [
            'code' => '12345',
        ]);

        $response->assertStatus(200)->assertJson(['verified' => true]);
    }

    public function test_gebruiker_wordt_geblokkeerd_met_verlopen_code(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        TwoFactorCode::query()->create([
            'user_id' => $user->id,
            'code' => Hash::make('123456'),
            'expires_at' => now()->subMinutes(1),
        ]);

        $response = $this->postJson('/api/auth/two-factor/challenge', [
            'code' => '123456',
        ]);

        $response->assertStatus(422);
    }

    public function test_code_kan_maar_eenmalig_gebruikt_worden(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        TwoFactorCode::query()->create([
            'user_id' => $user->id,
            'code' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->postJson('/api/auth/two-factor/challenge', ['code' => '123456']);
        $response = $this->postJson('/api/auth/two-factor/challenge', ['code' => '123456']);

        $response->assertStatus(422);
    }

    public function test_two_factor_challenge_geblokkeerd_na_vijf_pogingen(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/auth/two-factor/challenge', [
                'code' => '000000',
            ]);
        }

        $response = $this->postJson('/api/auth/two-factor/challenge', [
            'code' => '000000',
        ]);

        $response->assertStatus(429);
    }

    public function test_nieuwe_login_maakt_oude_2fa_code_ongeldig(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'otp@adventist.nl',
            'password' => bcrypt('wachtwoord123'),
        ]);

        TwoFactorCode::query()->create([
            'user_id' => $user->id,
            'code' => Hash::make('111111'),
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'otp@adventist.nl',
            'password' => 'wachtwoord123',
        ])->assertStatus(200);

        $this->actingAs($user);
        $response = $this->postJson('/api/auth/two-factor/challenge', [
            'code' => '111111',
        ]);

        $response->assertStatus(422);
    }

    public function test_opnieuw_versturen_maakt_oude_code_ongeldig_en_stuurt_mail(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $this->actingAs($user);

        TwoFactorCode::query()->create([
            'user_id' => $user->id,
            'code' => Hash::make('111111'),
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->postJson('/api/auth/two-factor/resend')
            ->assertStatus(200)
            ->assertJson(['sent' => true]);

        $plain = '';

        Mail::assertSent(TwoFactorCodeMail::class, function (TwoFactorCodeMail $mail) use (&$plain, $user): bool {
            $plain = $mail->code;

            return $mail->hasTo($user->email);
        });

        $this->postJson('/api/auth/two-factor/challenge', ['code' => '111111'])
            ->assertStatus(422);

        $this->postJson('/api/auth/two-factor/challenge', ['code' => $plain])
            ->assertStatus(200)
            ->assertJson(['verified' => true]);
    }

    public function test_opnieuw_versturen_geblokkeerd_na_drie_keer_in_tien_minuten(): void
    {
        Mail::fake();
        Cache::flush();

        $user = User::factory()->create();
        $this->actingAs($user);

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/auth/two-factor/resend')->assertStatus(200);
        }

        $this->postJson('/api/auth/two-factor/resend')->assertStatus(429);
    }
}

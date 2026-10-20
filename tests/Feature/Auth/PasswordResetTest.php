<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_wachtwoord_vergeten_mail_wordt_verstuurd(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'test@adventist.nl']);

        $response = $this->postJson('/api/auth/wachtwoord-vergeten', [
            'email' => 'test@adventist.nl',
        ]);

        $response->assertStatus(200);
    }

    public function test_wachtwoord_kan_gereset_worden_met_geldig_token(): void
    {
        $user = User::factory()->create(['email' => 'test@adventist.nl']);
        $token = Password::createToken($user);

        $response = $this->postJson('/api/auth/wachtwoord-reset', [
            'email' => 'test@adventist.nl',
            'token' => $token,
            'password' => 'NieuwWachtwoord123!',
            'password_confirmation' => 'NieuwWachtwoord123!',
        ]);

        $response->assertStatus(200);
    }

    public function test_wachtwoord_reset_roteert_remember_token(): void
    {
        $user = User::factory()->create([
            'email' => 'reset-token@adventist.nl',
            'remember_token' => 'oud-remember-token',
        ]);
        $token = Password::createToken($user);

        $this->postJson('/api/auth/wachtwoord-reset', [
            'email' => 'reset-token@adventist.nl',
            'token' => $token,
            'password' => 'NieuwWachtwoord123!',
            'password_confirmation' => 'NieuwWachtwoord123!',
        ])->assertStatus(200);

        $user->refresh();
        $this->assertNotSame('oud-remember-token', $user->remember_token);
        $this->assertNotNull($user->remember_token);
    }

    public function test_wachtwoord_vergeten_geblokkeerd_na_vijf_pogingen(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/auth/wachtwoord-vergeten', [
                'email' => 'flood@adventist.nl',
            ]);
        }

        $response = $this->postJson('/api/auth/wachtwoord-vergeten', [
            'email' => 'flood@adventist.nl',
        ]);

        $response->assertStatus(429);
    }

    public function test_wachtwoord_reset_weigert_wachtwoord_zonder_hoofdletter(): void
    {
        $user = User::factory()->create(['email' => 'zwak@adventist.nl']);
        $token = Password::createToken($user);

        $response = $this->postJson('/api/auth/wachtwoord-reset', [
            'email' => 'zwak@adventist.nl',
            'token' => $token,
            'password' => 'aaaaaaaa',
            'password_confirmation' => 'aaaaaaaa',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['password']);
    }

    public function test_wachtwoord_reset_accepteert_gemengde_case(): void
    {
        $user = User::factory()->create(['email' => 'sterk@adventist.nl']);
        $token = Password::createToken($user);

        $this->postJson('/api/auth/wachtwoord-reset', [
            'email' => 'sterk@adventist.nl',
            'token' => $token,
            'password' => 'Aa123456',
            'password_confirmation' => 'Aa123456',
        ])->assertStatus(200);
    }
}

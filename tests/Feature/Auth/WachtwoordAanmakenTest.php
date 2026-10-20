<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class WachtwoordAanmakenTest extends TestCase
{
    use RefreshDatabase;

    public function test_wachtwoord_kan_worden_aangemaakt_met_geldig_invite_token(): void
    {
        $user = User::factory()->create([
            'email' => 'nieuw@adventist.nl',
            'password' => Hash::make('tijdelijk-onbruikbaar'),
        ]);
        $token = Password::broker('invites')->createToken($user);

        $response = $this->postJson('/api/auth/wachtwoord-aanmaken', [
            'email' => 'nieuw@adventist.nl',
            'token' => $token,
            'password' => 'NieuwWachtwoord123!',
            'password_confirmation' => 'NieuwWachtwoord123!',
        ]);

        $response->assertStatus(200);
        $user->refresh();
        $this->assertTrue(Hash::check('NieuwWachtwoord123!', $user->password));
    }

    public function test_wachtwoord_aanmaken_faalt_met_ongeldig_token(): void
    {
        User::factory()->create(['email' => 'nieuw@adventist.nl']);

        $response = $this->postJson('/api/auth/wachtwoord-aanmaken', [
            'email' => 'nieuw@adventist.nl',
            'token' => 'ongeldig-token',
            'password' => 'NieuwWachtwoord123!',
            'password_confirmation' => 'NieuwWachtwoord123!',
        ]);

        $response->assertStatus(422);
    }

    public function test_wachtwoord_aanmaken_roteert_remember_token(): void
    {
        $user = User::factory()->create([
            'email' => 'invite-token@adventist.nl',
            'password' => Hash::make('tijdelijk-onbruikbaar'),
            'remember_token' => 'oud-invite-remember',
        ]);
        $token = Password::broker('invites')->createToken($user);

        $this->postJson('/api/auth/wachtwoord-aanmaken', [
            'email' => 'invite-token@adventist.nl',
            'token' => $token,
            'password' => 'NieuwWachtwoord123!',
            'password_confirmation' => 'NieuwWachtwoord123!',
        ])->assertStatus(200);

        $user->refresh();
        $this->assertNotSame('oud-invite-remember', $user->remember_token);
        $this->assertNotNull($user->remember_token);
    }

    public function test_reset_token_werkt_niet_op_wachtwoord_aanmaken(): void
    {
        $user = User::factory()->create([
            'email' => 'cross-broker@adventist.nl',
            'password' => Hash::make('oud'),
        ]);
        $resetToken = Password::broker('users')->createToken($user);

        $response = $this->postJson('/api/auth/wachtwoord-aanmaken', [
            'email' => 'cross-broker@adventist.nl',
            'token' => $resetToken,
            'password' => 'NieuwWachtwoord123!',
            'password_confirmation' => 'NieuwWachtwoord123!',
        ]);

        $response->assertStatus(422);
    }

    public function test_invite_broker_verloopt_na_72_uur(): void
    {
        $this->assertSame(4320, (int) config('auth.passwords.invites.expire'));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Mail\TwoFactorCodeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_gebruiker_kan_inloggen_met_correcte_gegevens(): void
    {
        Mail::fake();

        User::factory()->create([
            'email' => 'test@adventist.nl',
            'password' => bcrypt('wachtwoord123'),
            'two_factor_enabled' => true,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@adventist.nl',
            'password' => 'wachtwoord123',
        ]);

        $response->assertStatus(200)
            ->assertJson(['two_factor_required' => true]);

        Mail::assertSent(TwoFactorCodeMail::class);
    }

    public function test_login_zonder_2fa_wanneer_twee_factor_uit_in_config(): void
    {
        Mail::fake();
        Config::set('two_factor.enabled', false);

        User::factory()->create([
            'email' => 'dev@adventist.nl',
            'password' => bcrypt('wachtwoord123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'dev@adventist.nl',
            'password' => 'wachtwoord123',
        ]);

        $response->assertStatus(200)
            ->assertJson(['two_factor_required' => false]);
        Mail::assertNothingSent();
    }

    public function test_login_mislukt_met_verkeerd_wachtwoord(): void
    {
        User::factory()->create(['email' => 'test@adventist.nl']);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@adventist.nl',
            'password' => 'fout',
        ]);

        $response->assertStatus(422);
    }

    public function test_login_registreert_last_login_at(): void
    {
        Mail::fake();
        Config::set('two_factor.enabled', false);

        $user = User::factory()->create([
            'email' => 'login@adventist.nl',
            'password' => bcrypt('wachtwoord123'),
            'last_login_at' => null,
        ]);

        $this->assertNull($user->last_login_at);

        $this->postJson('/api/auth/login', [
            'email' => 'login@adventist.nl',
            'password' => 'wachtwoord123',
        ])->assertStatus(200);

        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_inactieve_gebruiker_kan_niet_inloggen(): void
    {
        User::factory()->create([
            'email' => 'inactief@adventist.nl',
            'password' => bcrypt('wachtwoord123'),
            'active' => false,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'inactief@adventist.nl',
            'password' => 'wachtwoord123',
        ]);

        $response->assertStatus(422)
            ->assertJson(['message' => __('api.auth.invalid_credentials')]);
    }

    public function test_login_geblokkeerd_na_vijf_pogingen(): void
    {
        User::factory()->create(['email' => 'test@adventist.nl']);

        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/auth/login', [
                'email' => 'test@adventist.nl',
                'password' => 'fout',
            ]);
        }

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@adventist.nl',
            'password' => 'fout',
        ]);

        $response->assertStatus(429);
    }

    public function test_auth_me_geeft_geen_user_voor_2fa(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(200)
            ->assertJson([
                'user' => null,
                'two_factor_pending' => true,
            ]);
    }

    public function test_auth_me_geeft_user_na_2fa(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonMissing(['two_factor_pending' => true]);
    }

    public function test_inactieve_sessie_wordt_geweigerd_maar_logout_blijft_mogelijk(): void
    {
        $user = User::factory()->create(['active' => true]);
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $user->forceFill(['active' => false])->save();

        $this->getJson('/api/predikant/beurten')
            ->assertStatus(403)
            ->assertJson(['message' => __('api.auth.account_inactive')]);

        $this->getJson('/api/auth/me')
            ->assertStatus(403)
            ->assertJson(['message' => __('api.auth.account_inactive')]);

        $this->postJson('/api/auth/logout')
            ->assertStatus(200);
    }
}

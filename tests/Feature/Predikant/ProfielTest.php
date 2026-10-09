<?php

declare(strict_types=1);

namespace Tests\Feature\Predikant;

use App\Mail\ProfielGewijzigdMail;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ProfielTest extends TestCase
{
    use RefreshDatabase;

    public function test_predikant_kan_eigen_profiel_ophalen(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->firstOrCreate(['slug' => 'predikant'], ['naam' => 'Predikant']));
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $response = $this->getJson('/api/predikant/profiel');

        $response->assertStatus(200)->assertJsonPath('data.email', $user->email);
    }

    public function test_predikant_profiel_weigert_letters_in_telefoonnummer(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->firstOrCreate(['slug' => 'predikant'], ['naam' => 'Predikant']));
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $this->putJson('/api/predikant/profiel', [
            'telefoonnummer' => 'abc',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['telefoonnummer']);
    }

    public function test_predikant_kan_profiel_bijwerken(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->firstOrCreate(['slug' => 'predikant'], ['naam' => 'Predikant']));
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $response = $this->putJson('/api/predikant/profiel', [
            'voornaam' => 'Jan',
            'achternaam' => 'Pietersen',
            'telefoonnummer' => '0612345678',
        ]);

        $response->assertStatus(200)->assertJsonPath('data.voornaam', 'Jan');
        Mail::assertSent(ProfielGewijzigdMail::class);
    }

    public function test_predikant_kan_voorkeurstaal_opslaan(): void
    {
        Mail::fake();
        $user = User::factory()->create(['taal' => 'nl']);
        $user->roles()->attach(Role::query()->firstOrCreate(['slug' => 'predikant'], ['naam' => 'Predikant']));
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $this->putJson('/api/predikant/profiel', [
            'taal' => 'en',
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.taal', 'en');

        $this->assertSame('en', $user->fresh()->taal);
    }

    public function test_email_wijzigen_vereist_uniek_adres(): void
    {
        User::factory()->create(['email' => 'bezet@adventist.nl']);
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->firstOrCreate(['slug' => 'predikant'], ['naam' => 'Predikant']));
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $response = $this->putJson('/api/predikant/profiel', [
            'voornaam' => 'Jan',
            'achternaam' => 'Jansen',
            'email' => 'bezet@adventist.nl',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['email']);
    }
}

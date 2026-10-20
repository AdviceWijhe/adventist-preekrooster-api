<?php

declare(strict_types=1);

namespace Tests\Feature\Predikant;

use App\Models\User;
use Database\Seeders\TaalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfielUitgebreidTest extends TestCase
{
    use RefreshDatabase;

    private function actingPredikant(array $attrs = []): User
    {
        $user = User::factory()->create($attrs);
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        return $user;
    }

    public function test_gebruiker_kan_talen_kiezen_en_nieuwe_taal_toevoegen(): void
    {
        Mail::fake();
        $this->seed(TaalSeeder::class);
        $user = $this->actingPredikant();

        $response = $this->putJson('/api/predikant/profiel', [
            'talen' => ['Nederlands', 'Papiaments'],
        ]);

        $response->assertStatus(200);
        $this->assertCount(2, $user->fresh()->talen);
        $this->assertDatabaseHas('talen', ['slug' => 'papiaments', 'naam' => 'Papiaments']);
    }

    public function test_nieuwe_taal_wordt_gedeeld_in_de_lijst(): void
    {
        Mail::fake();
        $this->seed(TaalSeeder::class);
        $this->actingPredikant();

        $this->putJson('/api/predikant/profiel', ['talen' => ['Zweeds']])->assertStatus(200);

        $lijst = $this->getJson('/api/predikant/talen');
        $lijst->assertStatus(200);
        $slugs = array_column($lijst->json('data'), 'slug');
        $this->assertContains('zweeds', $slugs);
        $this->assertContains('nederlands', $slugs);
    }

    public function test_gebruiker_kan_wachtwoord_wijzigen_met_juist_huidig_wachtwoord(): void
    {
        Mail::fake();
        $user = $this->actingPredikant([
            'password' => 'HuidigWW1!',
            'remember_token' => 'oud-profiel-remember',
        ]);

        $response = $this->putJson('/api/predikant/profiel', [
            'huidig_wachtwoord' => 'HuidigWW1!',
            'nieuw_wachtwoord' => 'NieuwGeheim2!',
            'nieuw_wachtwoord_confirmation' => 'NieuwGeheim2!',
        ]);

        $response->assertStatus(200);
        $user->refresh();
        $this->assertTrue(Hash::check('NieuwGeheim2!', $user->password));
        $this->assertNotSame('oud-profiel-remember', $user->remember_token);
        $this->assertNotNull($user->remember_token);
    }

    public function test_wachtwoord_wijzigen_faalt_bij_onjuist_huidig_wachtwoord(): void
    {
        Mail::fake();
        $user = $this->actingPredikant(['password' => 'HuidigWW1!']);

        $response = $this->putJson('/api/predikant/profiel', [
            'huidig_wachtwoord' => 'FoutWW',
            'nieuw_wachtwoord' => 'NieuwGeheim2!',
            'nieuw_wachtwoord_confirmation' => 'NieuwGeheim2!',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['huidig_wachtwoord']);
        $this->assertTrue(Hash::check('HuidigWW1!', $user->fresh()->password));
    }

    public function test_gebruiker_kan_profielfoto_uploaden_en_verwijderen(): void
    {
        Mail::fake();
        Storage::fake('public');
        $user = $this->actingPredikant();

        $upload = $this->post('/api/predikant/profiel/foto', [
            'foto' => UploadedFile::fake()->image('pasfoto.jpg', 200, 200),
        ], ['Accept' => 'application/json']);

        $upload->assertStatus(200);
        $pad = $user->fresh()->photo;
        $this->assertNotNull($pad);
        Storage::disk('public')->assertExists($pad);
        $this->assertNotNull($upload->json('data.photo_url'));

        $verwijder = $this->deleteJson('/api/predikant/profiel/foto');
        $verwijder->assertStatus(200);
        $this->assertNull($user->fresh()->photo);
        Storage::disk('public')->assertMissing($pad);
    }

    public function test_profielfoto_weigert_te_groot_of_verkeerd_type(): void
    {
        Mail::fake();
        Storage::fake('public');
        $this->actingPredikant();

        $response = $this->post('/api/predikant/profiel/foto', [
            'foto' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json', 'X-Locale' => 'nl']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['foto'])
            ->assertJsonPath('errors.foto.0', 'Upload een JPG- of PNG-afbeelding.');
    }

    public function test_profielfoto_max_grootte_melding_in_het_nederlands(): void
    {
        Mail::fake();
        Storage::fake('public');
        $this->actingPredikant();

        $response = $this->post('/api/predikant/profiel/foto', [
            'foto' => UploadedFile::fake()->create('groot.jpg', 3000, 'image/jpeg'),
        ], ['Accept' => 'application/json', 'X-Locale' => 'nl']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['foto'])
            ->assertJsonPath('errors.foto.0', 'De profielfoto mag maximaal 2 MB zijn.');
    }

    public function test_self_service_kan_geen_spreekniveau_of_geslacht_wijzigen(): void
    {
        Mail::fake();
        $user = $this->actingPredikant(['geslacht' => 'm', 'spreekniveau' => null]);

        $this->putJson('/api/predikant/profiel', [
            'voornaam' => 'Nieuw',
            'geslacht' => 'v',
            'spreekniveau' => 'predikant',
        ])->assertStatus(200);

        $vers = $user->fresh();
        $this->assertSame('Nieuw', $vers->voornaam);
        $this->assertSame('m', $vers->geslacht);
        $this->assertNull($vers->spreekniveau);
    }
}

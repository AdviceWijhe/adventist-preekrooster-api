<?php

declare(strict_types=1);

namespace Tests\Feature\Rooster;

use App\Models\Bijzonderheid;
use App\Models\Dienst;
use App\Models\Functie;
use App\Models\Gemeente;
use App\Models\Publicatie;
use App\Models\Role;
use App\Models\User;
use App\Models\UserBeschikbaarheid;
use Database\Seeders\FunctieSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RoosterKernTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_publiek_standaard_maand_is_beperkt_tot_huidige_en_volgende_maand(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-15', 'Europe/Amsterdam'));

        Publicatie::query()->create([
            'periode' => '2026-03',
            'gemeente_id' => null,
            'gepubliceerd' => true,
            'gepubliceerd_op' => now(),
        ]);
        Publicatie::query()->create([
            'periode' => '2026-04',
            'gemeente_id' => null,
            'gepubliceerd' => true,
            'gepubliceerd_op' => now(),
        ]);
        Publicatie::query()->create([
            'periode' => '2026-06',
            'gemeente_id' => null,
            'gepubliceerd' => true,
            'gepubliceerd_op' => now(),
        ]);

        $response = $this->getJson('/api/publiek/rooster/standaard-maand');

        $response->assertOk()
            ->assertJsonPath('maand', '2026-04')
            ->assertJsonPath('eerste_maand', '2026-04')
            ->assertJsonPath('laatste_maand', '2026-05')
            ->assertJsonPath('account_vereist_voor_volledig', true);
    }

    public function test_publiek_standaard_maand_schakelt_na_laatste_zaterdag(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-27', 'Europe/Amsterdam'));

        Publicatie::query()->create([
            'periode' => '2026-08',
            'gemeente_id' => null,
            'gepubliceerd' => true,
            'gepubliceerd_op' => now(),
        ]);

        $response = $this->getJson('/api/publiek/rooster/standaard-maand');

        $response->assertOk()->assertJsonPath('maand', '2026-08');
    }

    public function test_publiek_matrix_weigert_maanden_buiten_twee_maanden_venster(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-15', 'Europe/Amsterdam'));

        Publicatie::query()->create([
            'periode' => '2026-03',
            'gemeente_id' => null,
            'gepubliceerd' => true,
            'gepubliceerd_op' => now(),
        ]);

        $response = $this->getJson('/api/publiek/rooster/matrix?maand=2026-03');

        $response->assertOk()
            ->assertJsonPath('gepubliceerd', false)
            ->assertJsonFragment([
                'message' => 'Alleen het rooster van de huidige en volgende maand is publiek zichtbaar. Log in voor het volledige rooster.',
            ]);
    }

    public function test_beschikbare_sprekers_filtert_op_functie_en_beschikbaarheid(): void
    {
        $this->seed(FunctieSeeder::class);
        $gemeente = Gemeente::factory()->create();
        $datum = now()->addWeek()->format('Y-m-d');

        $beschikbaar = User::factory()->create(['landelijk_actief' => true]);
        $beschikbaar->functies()->attach(Functie::query()->where('slug', 'predikant')->value('id'));

        $onbeschikbaar = User::factory()->create();
        $onbeschikbaar->functies()->attach(Functie::query()->where('slug', 'spreker')->value('id'));
        UserBeschikbaarheid::query()->create([
            'user_id' => $onbeschikbaar->id,
            'datum_van' => $datum,
            'datum_tot' => $datum,
        ]);

        $admin = User::factory()->create();
        $admin->roles()->attach(Role::query()->firstOrCreate(['slug' => 'admin'], ['naam' => 'Admin']));
        $this->actingAs($admin);
        session(['two_factor_verified' => true]);

        $response = $this->getJson('/api/beheer/roosters/beschikbare-sprekers?datum='.$datum.'&gemeente_id='.$gemeente->id);

        $response->assertOk()->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $beschikbaar->id);
        $response->assertJsonPath('data.0.landelijk_actief', true);
    }

    public function test_matrix_toont_bijzonderheid_en_open_plek(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-15', 'Europe/Amsterdam'));

        Publicatie::query()->create([
            'periode' => '2026-04',
            'gemeente_id' => null,
            'gepubliceerd' => true,
            'gepubliceerd_op' => now(),
        ]);

        $g = Gemeente::factory()->create(['district_id' => null, 'active' => true]);
        $bijz = Bijzonderheid::query()->create(['naam' => 'Avondmaal']);
        Dienst::factory()->create([
            'datum' => '2026-04-04',
            'gemeente_id' => $g->id,
            'type' => 'eredienst',
            'bijzonderheid_id' => $bijz->id,
            'eigeninvulling' => null,
        ]);

        $response = $this->getJson('/api/publiek/rooster/matrix?maand=2026-04');

        $response->assertOk()->assertJsonPath('gepubliceerd', true);
        $response->assertJsonFragment(['bijzonderheid' => 'Avondmaal', 'open_plek' => true]);
    }

    public function test_ingelogde_gebruiker_kan_verder_vooruit_kijken(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-15', 'Europe/Amsterdam'));

        Dienst::factory()->create([
            'datum' => '2026-08-02',
            'gemeente_id' => Gemeente::factory()->create()->id,
        ]);

        $user = User::factory()->create();
        $user->functies()->attach(Functie::query()->firstOrCreate(['slug' => 'predikant'], ['naam' => 'Predikant']));
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $response = $this->getJson('/api/predikant/rooster/matrix?maand=2026-08');

        $response->assertOk()->assertJsonPath('gepubliceerd', true);
        $this->assertNotEmpty($response->json('districts'));
    }
}

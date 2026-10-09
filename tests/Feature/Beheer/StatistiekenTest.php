<?php

declare(strict_types=1);

namespace Tests\Feature\Beheer;

use App\Models\Bijzonderheid;
use App\Models\Dienst;
use App\Models\Gemeente;
use App\Models\Role;
use App\Models\Spreekbeurt;
use App\Models\User;
use App\Services\Statistieken\StatistiekenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatistiekenTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_kan_statistieken_opvragen(): void
    {
        $admin = $this->adminGebruiker();

        $response = $this->getJson('/api/beheer/statistieken?jaar=2026');

        $response->assertStatus(200)->assertJsonStructure([
            'data' => [
                'sectie',
                'kpis',
                'series',
                'beurtenPerPredikant',
                'beurtenPerGemeente',
                'bijzonderheden',
                'bevestigingspercentage',
                'filters',
            ],
            'meta' => ['secties', 'kmEnabled'],
        ]);
    }

    public function test_beheerder_zonder_toegang_krijgt_geen_statistieken(): void
    {
        $beheerder = User::factory()->create(['statistieken_toegang' => false]);
        $beheerder->roles()->attach(Role::query()->firstOrCreate(['slug' => 'beheerder'], ['naam' => 'Beheerder']));
        $this->actingAs($beheerder);
        session(['two_factor_verified' => true]);

        $this->getJson('/api/beheer/statistieken?jaar=2026')->assertStatus(403);
    }

    public function test_beheerder_met_toegang_krijgt_wel_statistieken(): void
    {
        $beheerder = User::factory()->create(['statistieken_toegang' => true]);
        $beheerder->roles()->attach(Role::query()->firstOrCreate(['slug' => 'beheerder'], ['naam' => 'Beheerder']));
        $this->actingAs($beheerder);
        session(['two_factor_verified' => true]);

        $this->getJson('/api/beheer/statistieken?jaar=2026')->assertStatus(200);
    }

    public function test_statistieken_telt_bijzonderheden_en_filtert_op_gemeente(): void
    {
        $this->actingAs($this->adminGebruiker());
        session(['two_factor_verified' => true]);

        $gemeenteA = Gemeente::factory()->create(['naam' => 'Wijhe']);
        $gemeenteB = Gemeente::factory()->create(['naam' => 'Deventer']);
        $avondmaal = Bijzonderheid::query()->create(['naam' => 'Avondmaal']);

        Dienst::factory()->create([
            'gemeente_id' => $gemeenteA->id,
            'datum' => '2026-03-07',
            'bijzonderheid_id' => $avondmaal->id,
        ]);
        Dienst::factory()->create([
            'gemeente_id' => $gemeenteB->id,
            'datum' => '2026-04-04',
            'bijzonderheid_id' => $avondmaal->id,
        ]);

        $response = $this->getJson('/api/beheer/statistieken?jaar=2026&gemeente_id='.$gemeenteA->id);

        $response->assertOk();
        $response->assertJsonPath('data.bijzonderheden.0.aantal', 1);
        $response->assertJsonPath('data.filters.gemeente_id', $gemeenteA->id);
    }

    public function test_statistieken_export_levert_csv(): void
    {
        $this->actingAs($this->adminGebruiker());
        session(['two_factor_verified' => true]);

        $spreker = User::factory()->create(['voornaam' => 'Jan', 'achternaam' => 'Jansen']);
        $gemeente = Gemeente::factory()->create(['naam' => 'Wijhe']);
        $dienst = Dienst::factory()->create([
            'gemeente_id' => $gemeente->id,
            'datum' => '2026-05-02',
        ]);
        Spreekbeurt::factory()->create([
            'dienst_id' => $dienst->id,
            'spreker_id' => $spreker->id,
            'bevestigd' => 1,
        ]);

        $response = $this->get('/api/beheer/statistieken/export?jaar=2026');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=utf-8');
        $this->assertStringContainsString('Jan', $response->getContent());
        $this->assertStringContainsString('Wijhe', $response->getContent());
    }

    public function test_rooster_sectie_levert_verdelingen(): void
    {
        $this->actingAs($this->adminGebruiker());
        session(['two_factor_verified' => true]);

        Dienst::factory()->create(['datum' => '2026-02-01', 'type' => 'reguliere_dienst']);
        Dienst::factory()->create(['datum' => '2026-03-01', 'type' => 'reguliere_dienst']);

        $response = $this->getJson('/api/beheer/statistieken?jaar=2026&sectie=rooster');

        $response->assertOk();
        $response->assertJsonPath('data.sectie', StatistiekenService::SECTIE_ROOSTER);
        $response->assertJsonStructure(['data' => ['series' => ['typeVerdeling', 'taalVerdeling']]]);
    }

    public function test_predikanten_sectie_levert_werkbelasting(): void
    {
        $this->actingAs($this->adminGebruiker());
        session(['two_factor_verified' => true]);

        $spreker = User::factory()->create();
        $dienst = Dienst::factory()->create(['datum' => '2026-06-14']);
        Spreekbeurt::factory()->create([
            'dienst_id' => $dienst->id,
            'spreker_id' => $spreker->id,
            'bevestigd' => 1,
        ]);

        $response = $this->getJson('/api/beheer/statistieken?jaar=2026&sectie=predikanten');

        $response->assertOk();
        $response->assertJsonPath('data.sectie', StatistiekenService::SECTIE_PREDIKANTEN);
        $response->assertJsonStructure(['data' => ['kpis', 'tabellen', 'beurtenPerPredikant']]);
    }

    public function test_reizen_sectie_alleen_beschikbaar_met_feature_flag(): void
    {
        config(['statistieken.km_enabled' => false]);
        $this->actingAs($this->adminGebruiker());
        session(['two_factor_verified' => true]);

        $this->getJson('/api/beheer/statistieken?jaar=2026&sectie=reizen')
            ->assertStatus(422);

        config(['statistieken.km_enabled' => true]);

        $spreker = User::factory()->create();
        $dienst = Dienst::factory()->create(['datum' => '2026-07-05']);
        Spreekbeurt::factory()->create([
            'dienst_id' => $dienst->id,
            'spreker_id' => $spreker->id,
            'kilometers' => 42,
            'bevestigd' => 1,
        ]);

        $response = $this->getJson('/api/beheer/statistieken?jaar=2026&sectie=reizen');
        $response->assertOk();
        $response->assertJsonPath('data.kpis.totaalKm', 42);
    }

    public function test_jaren_geeft_alleen_jaartallen_met_diensten_terug(): void
    {
        $this->adminGebruiker();

        Dienst::factory()->create(['datum' => '2026-03-07']);
        Dienst::factory()->create(['datum' => '2025-01-04']);

        $response = $this->getJson('/api/beheer/statistieken/jaren');

        $response->assertOk();
        $this->assertSame([2026, 2025], $response->json('data'));
    }

    public function test_jaren_vereist_statistiek_toegang(): void
    {
        $beheerder = User::factory()->create(['statistieken_toegang' => false]);
        $beheerder->roles()->attach(Role::query()->firstOrCreate(['slug' => 'beheerder'], ['naam' => 'Beheerder']));
        $this->actingAs($beheerder);
        session(['two_factor_verified' => true]);

        $this->getJson('/api/beheer/statistieken/jaren')->assertStatus(403);
    }

    private function adminGebruiker(): User
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::query()->firstOrCreate(['slug' => 'admin'], ['naam' => 'Admin']));
        $this->actingAs($admin);
        session(['two_factor_verified' => true]);

        return $admin;
    }
}

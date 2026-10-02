<?php

declare(strict_types=1);

namespace Tests\Feature\Beheer;

use App\Models\Dienst;
use App\Models\Gemeente;
use App\Models\Role;
use App\Models\Spreekbeurt;
use App\Models\User;
use App\Models\UserBeschikbaarheid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DienstTest extends TestCase
{
    use RefreshDatabase;

    private function beheerder(): User
    {
        $user = User::factory()->create();
        $role = Role::query()->firstOrCreate(
            ['slug' => 'beheerder'],
            ['naam' => 'Beheerder']
        );
        $user->roles()->attach($role);

        return $user;
    }

    public function test_beheerder_kan_diensten_per_maand_opvragen(): void
    {
        $gemeente = Gemeente::factory()->create();
        Dienst::factory()->create(['datum' => '2026-05-04', 'gemeente_id' => $gemeente->id]);

        $user = $this->beheerder();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $response = $this->getJson('/api/beheer/roosters?maand=2026-05');

        $response->assertStatus(200)->assertJsonCount(1, 'data');
    }

    public function test_beheerder_kan_rooster_op_gemeente_filteren(): void
    {
        $gemeenteA = Gemeente::factory()->create();
        $gemeenteB = Gemeente::factory()->create();
        Dienst::factory()->create(['datum' => '2026-05-04', 'gemeente_id' => $gemeenteA->id]);
        Dienst::factory()->create(['datum' => '2026-05-11', 'gemeente_id' => $gemeenteB->id]);

        $dienstMetSpreekbeurt = Dienst::query()
            ->whereDate('datum', '2026-05-04')
            ->where('gemeente_id', $gemeenteA->id)
            ->firstOrFail();

        Spreekbeurt::factory()->create([
            'dienst_id' => $dienstMetSpreekbeurt->id,
            'spreker_id' => User::factory()->create([
                'voornaam' => 'Piet',
                'tussenvoegsel' => null,
                'achternaam' => 'Jansen',
                'photo' => 'photos/test.jpg',
            ])->id,
            'bevestigd' => 0,
        ]);

        $user = $this->beheerder();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $response = $this->getJson('/api/beheer/roosters?maand=2026-05&gemeente_id='.$gemeenteA->id);

        $response
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.gemeente_id', $gemeenteA->id)
            ->assertJsonPath('data.0.gemeente_naam', $gemeenteA->naam)
            ->assertJsonPath('data.0.spreker_naam', 'Piet Jansen')
            ->assertJsonPath('data.0.status', 'afgewezen')
            ->assertJsonPath('data.0.gemeente.id', $gemeenteA->id)
            ->assertJsonPath('data.0.spreekbeurten.0.bevestigd', 0);

        $this->assertNotNull($response->json('data.0.spreekbeurten.0.spreker.photo_url'));
    }

    public function test_beheerder_kan_rooster_met_all_filter_opvragen(): void
    {
        $gemeenteA = Gemeente::factory()->create();
        $gemeenteB = Gemeente::factory()->create();
        Dienst::factory()->create(['datum' => '2026-05-04', 'gemeente_id' => $gemeenteA->id]);
        Dienst::factory()->create(['datum' => '2026-05-11', 'gemeente_id' => $gemeenteB->id]);

        $user = $this->beheerder();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $response = $this->getJson('/api/beheer/roosters?maand=2026-05&gemeente_id=all');

        $response->assertStatus(200)->assertJsonCount(2, 'data');
    }

    public function test_beheerder_krijgt_422_bij_ongeldige_gemeente_filter(): void
    {
        $user = $this->beheerder();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $response = $this->getJson('/api/beheer/roosters?maand=2026-05&gemeente_id=abc');

        $response->assertStatus(422);
    }

    public function test_beheerder_kan_dienst_aanmaken(): void
    {
        $gemeente = Gemeente::factory()->create();
        $user = $this->beheerder();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $response = $this->postJson('/api/beheer/roosters', [
            'datum' => '2026-05-04',
            'gemeente_id' => $gemeente->id,
            'type' => 'sabbatschool',
        ]);

        $response->assertStatus(201);
        $this->assertTrue(
            Dienst::query()
                ->whereDate('datum', '2026-05-04')
                ->where('gemeente_id', $gemeente->id)
                ->exists()
        );
    }

    public function test_beheerder_kan_dienst_aanmaken_met_taal_en_dienstwijze(): void
    {
        $gemeente = Gemeente::factory()->create();
        $user = $this->beheerder();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $response = $this->postJson('/api/beheer/roosters', [
            'datum' => '2026-05-20',
            'gemeente_id' => $gemeente->id,
            'type' => 'eredienst',
            'taal' => 'en',
            'dienstwijze' => 'fysiek_digitaal',
        ]);

        $response->assertStatus(201);
        $dienst = Dienst::query()
            ->whereDate('datum', '2026-05-20')
            ->where('gemeente_id', $gemeente->id)
            ->firstOrFail();
        $this->assertSame('en', $dienst->taal);
        $this->assertSame('fysiek_digitaal', $dienst->dienstwijze);
    }

    public function test_beheer_lijst_tonen_taal_en_dienstwijze(): void
    {
        $gemeente = Gemeente::factory()->create();
        Dienst::factory()->create([
            'datum' => '2026-05-04',
            'gemeente_id' => $gemeente->id,
            'taal' => 'en',
            'dienstwijze' => 'digitaal',
        ]);

        $user = $this->beheerder();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $response = $this->getJson('/api/beheer/roosters?maand=2026-05&gemeente_id='.$gemeente->id);

        $response
            ->assertStatus(200)
            ->assertJsonPath('data.0.taal', 'en')
            ->assertJsonPath('data.0.dienstwijze', 'digitaal');
    }

    public function test_datum_gemeente_combinatie_moet_uniek_zijn(): void
    {
        $gemeente = Gemeente::factory()->create();
        Dienst::factory()->create(['datum' => '2026-05-04', 'gemeente_id' => $gemeente->id, 'type' => 'sabbatschool']);

        $user = $this->beheerder();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $response = $this->postJson('/api/beheer/roosters', [
            'datum' => '2026-05-04',
            'gemeente_id' => $gemeente->id,
            'type' => 'eredienst',
        ]);

        $response->assertStatus(422);
    }

    public function test_beschikbare_sprekers_slaat_predikanten_over_die_niet_beschikbaar_zijn(): void
    {
        $gemeente = Gemeente::factory()->create();
        $predikantRole = Role::query()->firstOrCreate(['slug' => 'predikant'], ['naam' => 'Predikant']);

        $afwezig = User::factory()->create(['active' => true]);
        $afwezig->roles()->attach($predikantRole);
        UserBeschikbaarheid::query()->create([
            'user_id' => $afwezig->id,
            'datum_van' => '2026-08-01',
            'datum_tot' => '2026-08-20',
            'opmerking' => 'Vakantie',
        ]);

        $vrij = User::factory()->create(['active' => true, 'achternaam' => 'Vrij', 'photo' => 'photos/vrij.jpg']);
        $vrij->roles()->attach($predikantRole);

        $user = $this->beheerder();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $response = $this->getJson(
            '/api/beheer/roosters/beschikbare-sprekers?datum=2026-08-10&gemeente_id='.$gemeente->id
        );

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($vrij->id));
        $this->assertFalse($ids->contains($afwezig->id));

        $vrijInLijst = collect($response->json('data'))->firstWhere('id', $vrij->id);
        $this->assertNotNull($vrijInLijst['photo_url'] ?? null);
    }

    public function test_beschikbare_sprekers_slaat_al_ingeplande_spreker_over(): void
    {
        $gemeente = Gemeente::factory()->create();
        $predikantRole = Role::query()->firstOrCreate(['slug' => 'predikant'], ['naam' => 'Predikant']);
        $spreker = User::factory()->create(['active' => true, 'achternaam' => 'Bezet']);
        $spreker->roles()->attach($predikantRole);

        $dienst = Dienst::factory()->create([
            'datum' => '2026-08-10',
            'gemeente_id' => $gemeente->id,
            'type' => 'sabbatschool',
        ]);
        Spreekbeurt::query()->create([
            'dienst_id' => $dienst->id,
            'spreker_id' => $spreker->id,
            'bevestigd' => 1,
        ]);

        $user = $this->beheerder();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $response = $this->getJson(
            '/api/beheer/roosters/beschikbare-sprekers?datum=2026-08-10&gemeente_id='.$gemeente->id
        );

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($spreker->id));
    }
}

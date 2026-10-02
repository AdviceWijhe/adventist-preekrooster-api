<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Dienst;
use App\Models\Gemeente;
use App\Models\Option;
use App\Models\Publicatie;
use App\Models\Role;
use App\Models\Spreekbeurt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PubliekInschrijfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-04-15', 'Europe/Amsterdam'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function publiceerApril(): void
    {
        Publicatie::query()->create([
            'periode' => '2026-04',
            'gemeente_id' => null,
            'gepubliceerd' => true,
            'gepubliceerd_op' => now(),
        ]);
    }

    private function maakOpenDienst(): Dienst
    {
        $gemeente = Gemeente::factory()->create(['district_id' => null, 'active' => true]);

        return Dienst::factory()->create([
            'datum' => '2026-04-04',
            'gemeente_id' => $gemeente->id,
            'type' => 'eredienst',
            'eigeninvulling' => null,
        ]);
    }

    public function test_inschrijf_vereist_login(): void
    {
        $this->publiceerApril();
        $dienst = $this->maakOpenDienst();

        $response = $this->postJson("/api/publiek/rooster/diensten/{$dienst->id}/inschrijf");

        $response->assertUnauthorized();
    }

    public function test_inschrijf_verboden_voor_niet_predikant(): void
    {
        $this->publiceerApril();
        $dienst = $this->maakOpenDienst();
        $gebruiker = User::factory()->create();

        $this->actingAs($gebruiker);
        session(['two_factor_verified' => true]);

        $response = $this->postJson("/api/publiek/rooster/diensten/{$dienst->id}/inschrijf");

        $response->assertForbidden();
    }

    public function test_inschrijf_422_voor_bezette_dienst(): void
    {
        $this->publiceerApril();
        $dienst = $this->maakOpenDienst();
        $andere = User::factory()->create();
        $andere->roles()->attach(Role::query()->firstOrCreate(['slug' => 'predikant'], ['naam' => 'Predikant']));
        Spreekbeurt::factory()->create(['dienst_id' => $dienst->id, 'spreker_id' => $andere->id]);

        $predikant = User::factory()->create();
        $predikant->roles()->attach(Role::query()->firstOrCreate(['slug' => 'predikant'], ['naam' => 'Predikant']));
        $this->actingAs($predikant);
        session(['two_factor_verified' => true]);

        $response = $this->postJson("/api/publiek/rooster/diensten/{$dienst->id}/inschrijf");

        $response->assertStatus(422)->assertJsonValidationErrors('dienst');
    }

    public function test_inschrijf_422_bij_vergrendeld_rooster(): void
    {
        $this->publiceerApril();
        $dienst = $this->maakOpenDienst();
        Option::setValue('rooster_vergrendeld', '1');

        $predikant = User::factory()->create();
        $predikant->roles()->attach(Role::query()->firstOrCreate(['slug' => 'predikant'], ['naam' => 'Predikant']));
        $this->actingAs($predikant);
        session(['two_factor_verified' => true]);

        $response = $this->postJson("/api/publiek/rooster/diensten/{$dienst->id}/inschrijf");

        $response->assertStatus(422)->assertJsonValidationErrors('dienst');
    }

    public function test_inschrijf_succes_maar_publiek_matrix_verbergt_onbevestigde_spreker(): void
    {
        $this->publiceerApril();
        $dienst = $this->maakOpenDienst();

        $predikant = User::factory()->create([
            'voornaam' => 'Piet',
            'achternaam' => 'Pietersen',
        ]);
        $predikant->roles()->attach(Role::query()->firstOrCreate(['slug' => 'predikant'], ['naam' => 'Predikant']));
        $this->actingAs($predikant);
        session(['two_factor_verified' => true]);

        $response = $this->postJson("/api/publiek/rooster/diensten/{$dienst->id}/inschrijf");

        $response->assertCreated();
        $this->assertDatabaseHas('spreekbeurten', [
            'dienst_id' => $dienst->id,
            'spreker_id' => $predikant->id,
        ]);

        $matrix = $this->getJson('/api/publiek/rooster/matrix?maand=2026-04');
        $matrix->assertOk()->assertJsonFragment(['weergave' => '—']);
        $matrix->assertJsonMissing(['weergave' => 'Piet Pietersen']);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Dienst;
use App\Models\District;
use App\Models\Gemeente;
use App\Models\Publicatie;
use App\Models\Spreekbeurt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PubliekMatrixTest extends TestCase
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

    public function test_publiek_standaard_maand_is_laatst_gepubliceerde_periode(): void
    {
        Publicatie::query()->create([
            'periode' => '2026-04',
            'gemeente_id' => null,
            'gepubliceerd' => true,
            'gepubliceerd_op' => now(),
        ]);
        Publicatie::query()->create([
            'periode' => '2026-05',
            'gemeente_id' => null,
            'gepubliceerd' => true,
            'gepubliceerd_op' => now(),
        ]);

        $response = $this->getJson('/api/publiek/rooster/standaard-maand');

        $response->assertOk()
            ->assertJsonPath('maand', '2026-04')
            ->assertJsonPath('laatste_maand', '2026-05')
            ->assertJsonPath('eerste_maand', '2026-04');
    }

    public function test_publiek_matrix_ongepubliceerd_geeft_melding(): void
    {
        $response = $this->getJson('/api/publiek/rooster/matrix?maand=2026-04');

        $response->assertOk()
            ->assertJsonPath('gepubliceerd', false)
            ->assertJsonPath('districts', []);
        $this->assertArrayHasKey('saturdays', $response->json());
    }

    public function test_publiek_matrix_gepubliceerd_bevat_saturdays_en_diensten(): void
    {
        Publicatie::query()->create([
            'periode' => '2026-04',
            'gemeente_id' => null,
            'gepubliceerd' => true,
            'gepubliceerd_op' => now(),
        ]);

        $g = Gemeente::factory()->create(['district_id' => null, 'active' => true]);
        Dienst::factory()->create([
            'datum' => '2026-04-04',
            'gemeente_id' => $g->id,
            'type' => 'reguliere_dienst',
            'eigeninvulling' => null,
        ]);

        $response = $this->getJson('/api/publiek/rooster/matrix?maand=2026-04');
        $response->assertOk()
            ->assertJsonPath('gepubliceerd', true);
        $this->assertNotEmpty($response->json('saturdays'));
        $response->assertJsonFragment(['weergave' => '—']);
    }

    public function test_publiek_matrix_toont_spreker_naam_als_die_is_gekoppeld(): void
    {
        Publicatie::query()->create([
            'periode' => '2026-04',
            'gemeente_id' => null,
            'gepubliceerd' => true,
            'gepubliceerd_op' => now(),
        ]);

        $g = Gemeente::factory()->create(['district_id' => null, 'active' => true]);
        $dienst = Dienst::factory()->create([
            'datum' => '2026-04-04',
            'gemeente_id' => $g->id,
            'type' => 'reguliere_dienst',
            'eigeninvulling' => null,
        ]);
        $spreker = User::factory()->create([
            'voornaam' => 'Jan',
            'tussenvoegsel' => null,
            'achternaam' => 'Jansen',
            'photo' => 'profielfotos/jan.jpg',
        ]);
        Spreekbeurt::factory()->create([
            'dienst_id' => $dienst->id,
            'spreker_id' => $spreker->id,
            'bevestigd' => 1,
        ]);

        $response = $this->getJson('/api/publiek/rooster/matrix?maand=2026-04');

        $response->assertOk()->assertJsonPath('gepubliceerd', true);
        $response->assertJsonFragment(['weergave' => 'Jan Jansen']);
        $this->assertNotNull($response->json('districts.0.gemeentes.0.cellen.2026-04-04.diensten.0.spreker_photo_url'));
    }

    public function test_publiek_matrix_verbergt_onbevestigde_spreker(): void
    {
        Publicatie::query()->create([
            'periode' => '2026-04',
            'gemeente_id' => null,
            'gepubliceerd' => true,
            'gepubliceerd_op' => now(),
        ]);

        $g = Gemeente::factory()->create(['district_id' => null, 'active' => true]);
        $dienst = Dienst::factory()->create([
            'datum' => '2026-04-04',
            'gemeente_id' => $g->id,
            'type' => 'reguliere_dienst',
            'eigeninvulling' => null,
        ]);
        $spreker = User::factory()->create([
            'voornaam' => 'Piet',
            'achternaam' => 'Pietersen',
        ]);
        Spreekbeurt::factory()->create([
            'dienst_id' => $dienst->id,
            'spreker_id' => $spreker->id,
            'bevestigd' => null,
        ]);

        $response = $this->getJson('/api/publiek/rooster/matrix?maand=2026-04');

        $response->assertOk()->assertJsonPath('gepubliceerd', true);
        $response->assertJsonFragment(['weergave' => '—']);
        $response->assertJsonMissing(['weergave' => 'Piet Pietersen']);
    }

    public function test_publiek_matrix_bevat_gemeente_detailinformatie_voor_popup(): void
    {
        Publicatie::query()->create([
            'periode' => '2026-04',
            'gemeente_id' => null,
            'gepubliceerd' => true,
            'gepubliceerd_op' => now(),
        ]);

        Gemeente::factory()->create([
            'district_id' => null,
            'active' => true,
            'naam' => 'Schiedam: Oasis',
            'kerk' => 'Kerkcentrum Holy',
            'begintijd_ochtend' => '12:00:00',
            'begintijd_avond' => '11:00:00',
            'adres' => 'Reigerlaan 47A',
            'postcode' => '3136 JJ',
            'plaats' => 'Vlaardingen',
            'website_url' => 'https://example.org',
            'livestream_url' => 'https://youtube.com/example',
        ]);

        $response = $this->getJson('/api/publiek/rooster/matrix?maand=2026-04');

        $response->assertOk()->assertJsonPath('gepubliceerd', true);
        $response->assertJsonPath('districts.0.gemeentes.0.kerk', 'Kerkcentrum Holy');
        $response->assertJsonPath('districts.0.gemeentes.0.begintijd_eredienst', '12:00');
        $response->assertJsonPath('districts.0.gemeentes.0.begintijd_sabbatschool', '11:00');
        $response->assertJsonPath('districts.0.gemeentes.0.adres', 'Reigerlaan 47A');
        $response->assertJsonPath('districts.0.gemeentes.0.postcode', '3136 JJ');
        $response->assertJsonPath('districts.0.gemeentes.0.plaats', 'Vlaardingen');
        $response->assertJsonPath('districts.0.gemeentes.0.website_url', 'https://example.org');
        $response->assertJsonPath('districts.0.gemeentes.0.livestream_url', 'https://youtube.com/example');
    }

    public function test_publiek_matrix_verbergt_gemeentes_van_onzichtbaar_district(): void
    {
        Publicatie::query()->create([
            'periode' => '2026-04',
            'gemeente_id' => null,
            'gepubliceerd' => true,
            'gepubliceerd_op' => now(),
        ]);

        $zichtbaar = District::factory()->create(['naam' => 'Zichtbaar District', 'visible' => true]);
        $verborgen = District::factory()->create(['naam' => 'Verborgen District', 'visible' => false]);

        Gemeente::factory()->create([
            'naam' => 'Zichtbare Gemeente',
            'district_id' => $zichtbaar->id,
            'active' => true,
        ]);
        Gemeente::factory()->create([
            'naam' => 'Verborgen Gemeente',
            'district_id' => $verborgen->id,
            'active' => true,
        ]);
        Gemeente::factory()->create([
            'naam' => 'Losse Gemeente',
            'district_id' => null,
            'active' => true,
        ]);

        $response = $this->getJson('/api/publiek/rooster/matrix?maand=2026-04');

        $response->assertOk()->assertJsonPath('gepubliceerd', true);
        $response->assertJsonFragment(['naam' => 'Zichtbare Gemeente']);
        $response->assertJsonFragment(['naam' => 'Zichtbaar District']);
        $response->assertJsonFragment(['naam' => 'Losse Gemeente']);
        $response->assertJsonMissing(['naam' => 'Verborgen Gemeente']);
        $response->assertJsonMissing(['naam' => 'Verborgen District']);
    }
}

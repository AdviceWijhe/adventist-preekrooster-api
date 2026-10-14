<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Dienst;
use App\Models\Gemeente;
use App\Models\Publicatie;
use App\Models\PublicNavigationItem;
use App\Models\Spreekbeurt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PubliekRoosterTest extends TestCase
{
    use RefreshDatabase;

    public function test_publiek_rooster_is_toegankelijk_zonder_login(): void
    {
        $response = $this->getJson('/api/publiek/rooster?maand=2026-05');

        $response->assertStatus(200)->assertJsonStructure(['data']);
    }

    public function test_maand_parameter_is_verplicht(): void
    {
        $response = $this->getJson('/api/publiek/rooster');

        $response->assertStatus(422);
    }

    public function test_publiek_rooster_kan_op_gemeente_filteren(): void
    {
        $gemeenteA = Gemeente::factory()->create();
        $gemeenteB = Gemeente::factory()->create();

        Publicatie::query()->create([
            'periode' => '2026-05',
            'gemeente_id' => null,
            'gepubliceerd' => true,
        ]);

        Dienst::factory()->create([
            'datum' => '2026-05-04',
            'gemeente_id' => $gemeenteA->id,
        ]);
        Dienst::factory()->create([
            'datum' => '2026-05-11',
            'gemeente_id' => $gemeenteB->id,
        ]);

        $dienstMetSpreekbeurt = Dienst::query()
            ->whereDate('datum', '2026-05-04')
            ->where('gemeente_id', $gemeenteA->id)
            ->firstOrFail();

        Spreekbeurt::factory()->create([
            'dienst_id' => $dienstMetSpreekbeurt->id,
            'spreker_id' => User::factory()->create([
                'voornaam' => 'Jan',
                'tussenvoegsel' => 'van',
                'achternaam' => 'Dijk',
            ])->id,
            'bevestigd' => 1,
        ]);

        $response = $this->getJson('/api/publiek/rooster?maand=2026-05&gemeente_id='.$gemeenteA->id);

        $response
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.gemeente_id', $gemeenteA->id)
            ->assertJsonPath('data.0.gemeente_naam', $gemeenteA->naam)
            ->assertJsonPath('data.0.gemeente', $gemeenteA->naam)
            ->assertJsonPath('data.0.spreker_naam', 'Jan van Dijk')
            ->assertJsonPath('data.0.status', 'bevestigd')
            ->assertJsonPath('data.0.gemeente_object.id', $gemeenteA->id)
            ->assertJsonMissingPath('data.0.spreekbeurten');
    }

    public function test_publiek_rooster_lekt_geen_interne_spreekbeurtvelden_en_verbergt_niet_bevestigde(): void
    {
        $gemeente = Gemeente::factory()->create();
        $ingevoerdDoor = User::factory()->create();

        Publicatie::query()->create([
            'periode' => '2026-05',
            'gemeente_id' => null,
            'gepubliceerd' => true,
        ]);

        $dienst = Dienst::factory()->create([
            'datum' => '2026-05-04',
            'gemeente_id' => $gemeente->id,
        ]);

        Spreekbeurt::factory()->create([
            'dienst_id' => $dienst->id,
            'spreker_id' => User::factory()->create([
                'voornaam' => 'Piet',
                'tussenvoegsel' => null,
                'achternaam' => 'Jansen',
            ])->id,
            'bevestigd' => 0,
            'bericht' => 'Interne afwijzingsreden',
            'kilometers' => 42,
            'ingevoerd_door' => $ingevoerdDoor->id,
        ]);

        $response = $this->getJson('/api/publiek/rooster?maand=2026-05&gemeente_id='.$gemeente->id);

        $response
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.spreker_naam', null)
            ->assertJsonMissingPath('data.0.spreekbeurten');

        $this->assertNotSame('afgewezen', $response->json('data.0.status'));

        $payload = json_encode($response->json());
        $this->assertIsString($payload);
        $this->assertStringNotContainsString('Interne afwijzingsreden', $payload);
        $this->assertStringNotContainsString('"kilometers":42', $payload);
        $this->assertStringNotContainsString('"ingevoerd_door":'.$ingevoerdDoor->id, $payload);
        $this->assertStringNotContainsString('"bericht"', $payload);
    }

    public function test_publiek_rooster_accepteert_gemeente_id_all(): void
    {
        $gemeenteA = Gemeente::factory()->create();
        $gemeenteB = Gemeente::factory()->create();

        Publicatie::query()->create([
            'periode' => '2026-05',
            'gemeente_id' => null,
            'gepubliceerd' => true,
        ]);

        Dienst::factory()->create(['datum' => '2026-05-04', 'gemeente_id' => $gemeenteA->id]);
        Dienst::factory()->create(['datum' => '2026-05-11', 'gemeente_id' => $gemeenteB->id]);

        $response = $this->getJson('/api/publiek/rooster?maand=2026-05&gemeente_id=all');

        $response->assertStatus(200)->assertJsonCount(2, 'data');
    }

    public function test_publiek_rooster_valideert_ongeldige_gemeente_filter(): void
    {
        Publicatie::query()->create([
            'periode' => '2026-05',
            'gemeente_id' => null,
            'gepubliceerd' => true,
        ]);

        $response = $this->getJson('/api/publiek/rooster?maand=2026-05&gemeente_id=abc');

        $response->assertStatus(422);
    }

    public function test_publieke_navigatie_endpoint_geeft_consistente_response_envelope(): void
    {
        PublicNavigationItem::query()->create([
            'key' => 'home',
            'label_nl' => 'Start',
            'label_en' => 'Home',
            'url' => '/',
            'display_order' => 20,
            'visible' => true,
            'external' => false,
        ]);
        PublicNavigationItem::query()->create([
            'key' => 'stream',
            'label_nl' => 'Livestream',
            'label_en' => 'Livestream',
            'url' => 'https://example.org/live',
            'display_order' => 30,
            'visible' => true,
            'external' => true,
        ]);

        $response = $this->getJson('/api/publiek/navigatie');

        $response
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('error', null)
            ->assertJsonPath('data.0.id', 'home')
            ->assertJsonPath('data.0.label_nl', 'Start')
            ->assertJsonPath('data.1.external', true);
    }

    public function test_publieke_navigatie_verbergt_onzichtbare_items(): void
    {
        PublicNavigationItem::query()->create([
            'key' => 'zichtbaar',
            'label_nl' => 'Zichtbaar',
            'label_en' => 'Visible',
            'url' => '/',
            'display_order' => 10,
            'visible' => true,
            'external' => false,
        ]);
        PublicNavigationItem::query()->create([
            'key' => 'concept',
            'label_nl' => 'Concept',
            'label_en' => 'Draft',
            'url' => '/concept',
            'display_order' => 20,
            'visible' => false,
            'external' => false,
        ]);

        $response = $this->getJson('/api/publiek/navigatie');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 'zichtbaar')
            ->assertJsonMissing(['id' => 'concept']);
    }
}

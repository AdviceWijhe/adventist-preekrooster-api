<?php

declare(strict_types=1);

namespace Tests\Feature\Predikant;

use App\Models\Dienst;
use App\Models\Gemeente;
use App\Models\Spreekbeurt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AgendaIcalTest extends TestCase
{
    use RefreshDatabase;

    private function actingPredikant(array $attrs = []): User
    {
        $user = User::factory()->create($attrs);
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        return $user;
    }

    public function test_predikant_kan_agenda_abonnement_url_ophalen(): void
    {
        Mail::fake();
        $user = $this->actingPredikant();

        $response = $this->getJson('/api/predikant/agenda');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => ['subscribe_url', 'webcal_url', 'token_created_at'],
        ]);

        $token = $user->fresh()->agenda_token;
        $this->assertNotNull($token);
        $this->assertStringContainsString('/api/agenda/'.$token.'.ics', $response->json('data.subscribe_url'));
        $this->assertStringStartsWith('webcal://', $response->json('data.webcal_url'));
    }

    public function test_ics_feed_bevat_alleen_bevestigde_beurten(): void
    {
        Mail::fake();
        $user = $this->actingPredikant();
        $gemeente = Gemeente::factory()->create([
            'naam' => 'Wijhe',
            'begintijd_ochtend' => '11:00',
            'begintijd_avond' => '10:00',
        ]);

        $bevestigd = Dienst::factory()->create([
            'gemeente_id' => $gemeente->id,
            'datum' => now()->addWeek()->next('Saturday')->format('Y-m-d'),
            'type' => 'eredienst',
        ]);
        Spreekbeurt::factory()->create([
            'dienst_id' => $bevestigd->id,
            'spreker_id' => $user->id,
            'bevestigd' => 1,
        ]);

        $uitgevraagd = Dienst::factory()->create([
            'gemeente_id' => $gemeente->id,
            'datum' => now()->addWeeks(2)->next('Saturday')->format('Y-m-d'),
            'type' => 'sabbatschool',
        ]);
        Spreekbeurt::factory()->create([
            'dienst_id' => $uitgevraagd->id,
            'spreker_id' => $user->id,
            'bevestigd' => null,
        ]);

        $token = $user->ensureAgendaToken();
        $response = $this->get('/api/agenda/'.$token.'.ics');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
        $response->assertHeader('ETag');
        $response->assertHeader('Cache-Control', 'no-store, private');

        $body = $response->getContent();
        $this->assertStringContainsString('BEGIN:VCALENDAR', $body);
        $this->assertStringContainsString('Eredienst — Wijhe', $body);
        $this->assertStringContainsString('UID:spreekbeurt-', $body);
        $this->assertStringNotContainsString('Sabbatschool', $body);
    }

    public function test_ics_feed_geeft_404_bij_ongeldige_token(): void
    {
        $this->get('/api/agenda/'.str_repeat('a', 64).'.ics')->assertNotFound();
    }

    public function test_regenereren_token_maakt_oude_feed_ongeldig(): void
    {
        Mail::fake();
        $user = $this->actingPredikant();
        $oudToken = $user->ensureAgendaToken();

        $response = $this->postJson('/api/predikant/agenda/regenerate');
        $response->assertOk();

        $nieuwToken = $user->fresh()->agenda_token;
        $this->assertNotSame($oudToken, $nieuwToken);

        $this->get('/api/agenda/'.$oudToken.'.ics')->assertNotFound();
        $this->get('/api/agenda/'.$nieuwToken.'.ics')->assertOk();
    }

    public function test_ics_feed_ondersteunt_etag_304(): void
    {
        Mail::fake();
        $user = $this->actingPredikant();
        $token = $user->ensureAgendaToken();

        $first = $this->get('/api/agenda/'.$token.'.ics');
        $etag = $first->headers->get('ETag');

        $this->withHeader('If-None-Match', $etag)
            ->get('/api/agenda/'.$token.'.ics')
            ->assertStatus(304);
    }

    public function test_ics_feed_bevat_geen_spreekbeurt_bericht(): void
    {
        Mail::fake();
        $user = $this->actingPredikant();
        $gemeente = Gemeente::factory()->create(['naam' => 'Wijhe']);
        $dienst = Dienst::factory()->create([
            'gemeente_id' => $gemeente->id,
            'datum' => now()->addWeek()->next('Saturday')->format('Y-m-d'),
            'type' => 'eredienst',
        ]);
        Spreekbeurt::factory()->create([
            'dienst_id' => $dienst->id,
            'spreker_id' => $user->id,
            'bevestigd' => 1,
            'bericht' => 'Privéziekte-opmerking',
        ]);

        $token = $user->ensureAgendaToken();
        $body = $this->get('/api/agenda/'.$token.'.ics')->assertOk()->getContent();

        $this->assertStringNotContainsString('Privéziekte-opmerking', $body);
        $this->assertStringNotContainsString('Opmerking:', $body);
    }
}

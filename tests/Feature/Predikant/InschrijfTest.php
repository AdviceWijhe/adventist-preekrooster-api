<?php

declare(strict_types=1);

namespace Tests\Feature\Predikant;

use App\Mail\BeurtStatusMeldingMail;
use App\Mail\PredikantUitvraagMail;
use App\Models\Dienst;
use App\Models\Gemeente;
use App\Models\Option;
use App\Models\Publicatie;
use App\Models\Role;
use App\Models\Spreekbeurt;
use App\Models\User;
use App\Models\UserBeschikbaarheid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InschrijfTest extends TestCase
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

    private function maakPredikant(): User
    {
        $predikant = User::factory()->create([
            'voornaam' => 'Piet',
            'achternaam' => 'Pietersen',
        ]);
        $predikant->roles()->attach(Role::query()->firstOrCreate(['slug' => 'predikant'], ['naam' => 'Predikant']));

        return $predikant;
    }

    private function loginAlsPredikant(?User $predikant = null): User
    {
        $predikant ??= $this->maakPredikant();
        $this->actingAs($predikant);
        session(['two_factor_verified' => true]);

        return $predikant;
    }

    private function maakOpenDienst(string $datum = '2026-04-25'): Dienst
    {
        $contact = User::factory()->create(['email' => 'contact@example.test']);
        $gemeente = Gemeente::factory()->create([
            'district_id' => null,
            'active' => true,
            'contactpersoon_id' => $contact->id,
        ]);

        return Dienst::factory()->create([
            'datum' => $datum,
            'gemeente_id' => $gemeente->id,
            'type' => 'eredienst',
            'eigeninvulling' => null,
        ]);
    }

    private function inschrijfUrl(Dienst $dienst): string
    {
        return "/api/predikant/rooster/diensten/{$dienst->id}/inschrijf";
    }

    public function test_inschrijf_vereist_login(): void
    {
        $dienst = $this->maakOpenDienst();

        $response = $this->postJson($this->inschrijfUrl($dienst));

        $response->assertUnauthorized();
    }

    public function test_inschrijf_verboden_voor_niet_predikant(): void
    {
        $dienst = $this->maakOpenDienst();
        $gebruiker = User::factory()->create();

        $this->actingAs($gebruiker);
        session(['two_factor_verified' => true]);

        $response = $this->postJson($this->inschrijfUrl($dienst));

        $response->assertForbidden();
    }

    public function test_inschrijf_422_voor_bezette_dienst(): void
    {
        $dienst = $this->maakOpenDienst();
        $andere = $this->maakPredikant();
        Spreekbeurt::factory()->create(['dienst_id' => $dienst->id, 'spreker_id' => $andere->id]);

        $this->loginAlsPredikant();

        $response = $this->postJson($this->inschrijfUrl($dienst));

        $response->assertStatus(422)->assertJsonValidationErrors('dienst');
    }

    public function test_inschrijf_422_bij_vergrendeld_rooster(): void
    {
        $dienst = $this->maakOpenDienst();
        Option::setValue('rooster_vergrendeld', '1');

        $this->loginAlsPredikant();

        $response = $this->postJson($this->inschrijfUrl($dienst));

        $response->assertStatus(422)->assertJsonValidationErrors('dienst');
    }

    public function test_inschrijf_422_voor_dienst_in_verleden(): void
    {
        $dienst = $this->maakOpenDienst('2026-04-04');

        $this->loginAlsPredikant();

        $response = $this->postJson($this->inschrijfUrl($dienst));

        $response->assertStatus(422)->assertJsonValidationErrors('dienst');
    }

    public function test_inschrijf_422_bij_onbeschikbaarheid(): void
    {
        $dienst = $this->maakOpenDienst('2026-04-25');
        $predikant = $this->loginAlsPredikant();

        UserBeschikbaarheid::query()->create([
            'user_id' => $predikant->id,
            'datum_van' => '2026-04-20',
            'datum_tot' => '2026-04-30',
            'opmerking' => 'Vakantie',
        ]);

        $response = $this->postJson($this->inschrijfUrl($dienst));

        $response->assertStatus(422)->assertJsonValidationErrors('datum');
    }

    public function test_inschrijf_succes_toekomst_ongeplubliceerd_met_directe_bevestiging(): void
    {
        Mail::fake();

        $beheerder = User::factory()->create(['email' => 'beheerder@example.test']);
        $beheerder->roles()->attach(Role::query()->firstOrCreate(['slug' => 'beheerder'], ['naam' => 'Beheerder']));
        $admin = User::factory()->create(['email' => 'admin@example.test']);
        $admin->roles()->attach(Role::query()->firstOrCreate(['slug' => 'admin'], ['naam' => 'Admin']));

        $dienst = $this->maakOpenDienst('2026-05-02');
        $predikant = $this->loginAlsPredikant();

        $response = $this->postJson($this->inschrijfUrl($dienst));

        $response->assertCreated();
        $this->assertDatabaseHas('spreekbeurten', [
            'dienst_id' => $dienst->id,
            'spreker_id' => $predikant->id,
            'bevestigd' => 1,
        ]);

        Mail::assertNotSent(PredikantUitvraagMail::class);
        Mail::assertSent(BeurtStatusMeldingMail::class, fn (BeurtStatusMeldingMail $mail): bool => $mail->hasTo('contact@example.test'));
        Mail::assertNotSent(BeurtStatusMeldingMail::class, fn (BeurtStatusMeldingMail $mail): bool => $mail->hasTo('beheerder@example.test'));
        Mail::assertNotSent(BeurtStatusMeldingMail::class, fn (BeurtStatusMeldingMail $mail): bool => $mail->hasTo('admin@example.test'));
    }

    public function test_inschrijf_succes_gepubliceerde_maand_toont_bevestigde_spreker_in_matrix(): void
    {
        Mail::fake();

        Publicatie::query()->create([
            'periode' => '2026-04',
            'gemeente_id' => null,
            'gepubliceerd' => true,
            'gepubliceerd_op' => now(),
        ]);

        $dienst = $this->maakOpenDienst('2026-04-25');
        $this->loginAlsPredikant();

        $response = $this->postJson($this->inschrijfUrl($dienst));

        $response->assertCreated();

        $matrix = $this->getJson('/api/predikant/rooster/matrix?maand=2026-04');
        $matrix->assertOk()->assertJsonFragment(['weergave' => 'Piet Pietersen']);
    }
}

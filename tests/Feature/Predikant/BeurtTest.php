<?php

declare(strict_types=1);

namespace Tests\Feature\Predikant;

use App\Mail\BeurtAnnuleringMail;
use App\Mail\BeurtStatusMeldingMail;
use App\Models\Dienst;
use App\Models\Functie;
use App\Models\Gemeente;
use App\Models\Option;
use App\Models\Role;
use App\Models\Spreekbeurt;
use App\Models\User;
use App\Services\Instellingen\InstellingenService;
use Database\Seeders\FunctieSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BeurtTest extends TestCase
{
    use RefreshDatabase;

    public function test_predikant_kan_eigen_beurten_opvragen(): void
    {
        $predikant = User::factory()->create();
        $predikant->roles()->attach(Role::query()->firstOrCreate(['slug' => 'predikant'], ['naam' => 'Predikant']));
        $dienst = Dienst::factory()->create(['datum' => now()->addWeek()->format('Y-m-d')]);
        Spreekbeurt::factory()->create(['spreker_id' => $predikant->id, 'dienst_id' => $dienst->id]);

        $this->actingAs($predikant);
        session(['two_factor_verified' => true]);

        $response = $this->getJson('/api/predikant/beurten');

        $response->assertStatus(200)->assertJsonCount(1, 'data');
    }

    public function test_contactpersoon_mag_geen_beurten_inzien_of_bevestigen(): void
    {
        $this->seed(FunctieSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->firstOrCreate(['slug' => 'gebruiker'], ['naam' => 'Gebruiker']));
        $user->functies()->attach(Functie::query()->where('slug', 'contactpersoon')->value('id'));

        $dienst = Dienst::factory()->create(['datum' => now()->addWeek()->format('Y-m-d')]);
        $spreekbeurt = Spreekbeurt::factory()->create([
            'spreker_id' => $user->id,
            'dienst_id' => $dienst->id,
            'bevestigd' => null,
        ]);

        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $this->getJson('/api/predikant/beurten')->assertStatus(403);
        $this->getJson("/api/predikant/beurten/{$spreekbeurt->id}")->assertStatus(403);
        $this->postJson("/api/predikant/beurten/{$spreekbeurt->id}/bevestig", [
            'bevestigd' => 1,
        ])->assertStatus(403);
    }

    public function test_predikant_ziet_ook_beurten_in_het_verleden(): void
    {
        $predikant = User::factory()->create();
        $predikant->roles()->attach(Role::query()->firstOrCreate(['slug' => 'predikant'], ['naam' => 'Predikant']));
        $dienst = Dienst::factory()->create(['datum' => now()->subWeek()->format('Y-m-d')]);
        Spreekbeurt::factory()->create(['spreker_id' => $predikant->id, 'dienst_id' => $dienst->id]);

        $this->actingAs($predikant);
        session(['two_factor_verified' => true]);

        $response = $this->getJson('/api/predikant/beurten');

        $response->assertStatus(200)->assertJsonCount(1, 'data');
    }

    public function test_predikant_kan_eigen_beurt_opvragen(): void
    {
        $predikant = User::factory()->create();
        $predikant->roles()->attach(Role::query()->firstOrCreate(['slug' => 'predikant'], ['naam' => 'Predikant']));
        $beheerder = User::factory()->create(['voornaam' => 'Bea', 'achternaam' => 'Heerder']);
        $beheerder->roles()->attach(Role::query()->firstOrCreate(['slug' => 'beheerder'], ['naam' => 'Beheerder']));
        $gemeente = Gemeente::factory()->create([
            'adres' => 'Kerkstraat 1',
            'plaats' => 'Wijhe',
            'postcode' => '8131 AA',
            'kerk' => 'Adventkerk',
        ]);
        $dienst = Dienst::factory()->create(['datum' => now()->addWeek()->format('Y-m-d'), 'gemeente_id' => $gemeente->id]);
        $spreekbeurt = Spreekbeurt::factory()->create([
            'spreker_id' => $predikant->id,
            'dienst_id' => $dienst->id,
            'ingevoerd_door' => $beheerder->id,
        ]);

        $this->actingAs($predikant);
        session(['two_factor_verified' => true]);

        $response = $this->getJson("/api/predikant/beurten/{$spreekbeurt->id}");

        $response
            ->assertStatus(200)
            ->assertJsonPath('data.id', $spreekbeurt->id)
            ->assertJsonPath('data.dienst.gemeente.adres', 'Kerkstraat 1')
            ->assertJsonPath('data.ingevoerd_door.voornaam', 'Bea')
            ->assertJsonStructure(['data' => ['created_at']]);
    }

    public function test_predikant_kan_andermans_beurt_niet_opvragen(): void
    {
        $predikant = User::factory()->create();
        $predikant->roles()->attach(Role::query()->firstOrCreate(['slug' => 'predikant'], ['naam' => 'Predikant']));
        $anderePredikant = User::factory()->create();
        $dienst = Dienst::factory()->create(['datum' => now()->addWeek()->format('Y-m-d')]);
        $spreekbeurt = Spreekbeurt::factory()->create(['spreker_id' => $anderePredikant->id, 'dienst_id' => $dienst->id]);

        $this->actingAs($predikant);
        session(['two_factor_verified' => true]);

        $this->getJson("/api/predikant/beurten/{$spreekbeurt->id}")->assertStatus(403);
    }

    public function test_predikant_kan_beurt_bevestigen(): void
    {
        Mail::fake();
        $predikant = User::factory()->create();
        $predikant->roles()->attach(Role::query()->firstOrCreate(['slug' => 'predikant'], ['naam' => 'Predikant']));
        $beheerder = User::factory()->create(['email' => 'beheerder@example.test']);
        $beheerder->roles()->attach(Role::query()->firstOrCreate(['slug' => 'beheerder'], ['naam' => 'Beheerder']));
        $admin = User::factory()->create(['email' => 'admin@example.test']);
        $admin->roles()->attach(Role::query()->firstOrCreate(['slug' => 'admin'], ['naam' => 'Admin']));
        $contact = User::factory()->create(['email' => 'contact@example.test']);
        $gemeente = Gemeente::factory()->create(['contactpersoon_id' => $contact->id]);
        $dienst = Dienst::factory()->create(['datum' => now()->addWeek()->format('Y-m-d'), 'gemeente_id' => $gemeente->id]);
        $spreekbeurt = Spreekbeurt::factory()->create([
            'spreker_id' => $predikant->id,
            'dienst_id' => $dienst->id,
            'bevestigd' => null,
        ]);

        $this->actingAs($predikant);
        session(['two_factor_verified' => true]);

        $response = $this->postJson("/api/predikant/beurten/{$spreekbeurt->id}/bevestig", [
            'bevestigd' => 1,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('spreekbeurten', ['id' => $spreekbeurt->id, 'bevestigd' => 1]);
        Mail::assertSent(BeurtStatusMeldingMail::class, fn (BeurtStatusMeldingMail $mail): bool => $mail->hasTo('contact@example.test'));
        Mail::assertNotSent(BeurtStatusMeldingMail::class, fn (BeurtStatusMeldingMail $mail): bool => $mail->hasTo('beheerder@example.test'));
        Mail::assertNotSent(BeurtStatusMeldingMail::class, fn (BeurtStatusMeldingMail $mail): bool => $mail->hasTo('admin@example.test'));
    }

    public function test_afgewezen_beurt_blijft_zichtbaar_in_overzicht(): void
    {
        Mail::fake();
        $predikant = User::factory()->create();
        $predikant->roles()->attach(Role::query()->firstOrCreate(['slug' => 'predikant'], ['naam' => 'Predikant']));
        $dienst = Dienst::factory()->create(['datum' => now()->addWeek()->format('Y-m-d')]);
        $spreekbeurt = Spreekbeurt::factory()->create([
            'spreker_id' => $predikant->id,
            'dienst_id' => $dienst->id,
            'bevestigd' => null,
        ]);

        $this->actingAs($predikant);
        session(['two_factor_verified' => true]);

        $this->postJson("/api/predikant/beurten/{$spreekbeurt->id}/bevestig", ['bevestigd' => 0])
            ->assertStatus(200);

        $response = $this->getJson('/api/predikant/beurten');

        $response->assertStatus(200)->assertJsonCount(1, 'data');
        $this->assertSame(0, $response->json('data.0.bevestigd'));
    }

    public function test_predikant_kan_niet_andermans_beurt_bevestigen(): void
    {
        $predikant = User::factory()->create();
        $predikant->roles()->attach(Role::query()->firstOrCreate(['slug' => 'predikant'], ['naam' => 'Predikant']));
        $anderePredikant = User::factory()->create();
        $dienst = Dienst::factory()->create(['datum' => now()->addWeek()->format('Y-m-d')]);
        $spreekbeurt = Spreekbeurt::factory()->create(['spreker_id' => $anderePredikant->id, 'dienst_id' => $dienst->id]);

        $this->actingAs($predikant);
        session(['two_factor_verified' => true]);

        $response = $this->postJson("/api/predikant/beurten/{$spreekbeurt->id}/bevestig", ['bevestigd' => 1]);

        $response->assertStatus(403);
    }

    public function test_predikant_kan_bevestigde_beurt_annuleren_met_notitie(): void
    {
        Mail::fake();
        $predikant = User::factory()->create(['voornaam' => 'Jan', 'achternaam' => 'Prediker']);
        $predikant->roles()->attach(Role::query()->firstOrCreate(['slug' => 'predikant'], ['naam' => 'Predikant']));
        $contact = User::factory()->create(['email' => 'contact@gemeente.test']);
        $gemeente = Gemeente::factory()->create(['contactpersoon_id' => $contact->id, 'naam' => 'Wijhe']);
        $dienst = Dienst::factory()->create(['datum' => now()->addWeek()->format('Y-m-d'), 'gemeente_id' => $gemeente->id]);
        $spreekbeurt = Spreekbeurt::factory()->create([
            'spreker_id' => $predikant->id,
            'dienst_id' => $dienst->id,
            'bevestigd' => 1,
        ]);

        $this->actingAs($predikant);
        session(['two_factor_verified' => true]);

        $response = $this->postJson("/api/predikant/beurten/{$spreekbeurt->id}/annuleer", [
            'notitie' => 'Ik ben die week op vakantie.',
        ]);

        $response->assertStatus(204);
        $this->assertDatabaseMissing('spreekbeurten', ['id' => $spreekbeurt->id]);
        Mail::assertSent(BeurtAnnuleringMail::class, function ($mail) use ($contact): bool {
            return $mail->hasTo($contact->email);
        });
    }

    public function test_annuleren_vereist_notitie(): void
    {
        $predikant = User::factory()->create();
        $predikant->roles()->attach(Role::query()->firstOrCreate(['slug' => 'predikant'], ['naam' => 'Predikant']));
        $dienst = Dienst::factory()->create(['datum' => now()->addWeek()->format('Y-m-d')]);
        $spreekbeurt = Spreekbeurt::factory()->create([
            'spreker_id' => $predikant->id,
            'dienst_id' => $dienst->id,
            'bevestigd' => 1,
        ]);

        $this->actingAs($predikant);
        session(['two_factor_verified' => true]);

        $this->postJson("/api/predikant/beurten/{$spreekbeurt->id}/annuleer", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['notitie']);
    }

    public function test_predikant_kan_onbevestigde_beurt_niet_annuleren(): void
    {
        $predikant = User::factory()->create();
        $predikant->roles()->attach(Role::query()->firstOrCreate(['slug' => 'predikant'], ['naam' => 'Predikant']));
        $dienst = Dienst::factory()->create(['datum' => now()->addWeek()->format('Y-m-d')]);
        $spreekbeurt = Spreekbeurt::factory()->create([
            'spreker_id' => $predikant->id,
            'dienst_id' => $dienst->id,
            'bevestigd' => null,
        ]);

        $this->actingAs($predikant);
        session(['two_factor_verified' => true]);

        $this->postJson("/api/predikant/beurten/{$spreekbeurt->id}/annuleer", ['notitie' => 'Reden'])
            ->assertStatus(422);
    }

    public function test_predikant_kan_verlopen_beurt_niet_annuleren(): void
    {
        $predikant = User::factory()->create();
        $predikant->roles()->attach(Role::query()->firstOrCreate(['slug' => 'predikant'], ['naam' => 'Predikant']));
        $dienst = Dienst::factory()->create(['datum' => now()->subWeek()->format('Y-m-d')]);
        $spreekbeurt = Spreekbeurt::factory()->create([
            'spreker_id' => $predikant->id,
            'dienst_id' => $dienst->id,
            'bevestigd' => 1,
        ]);

        $this->actingAs($predikant);
        session(['two_factor_verified' => true]);

        $this->postJson("/api/predikant/beurten/{$spreekbeurt->id}/annuleer", ['notitie' => 'Reden'])
            ->assertStatus(422);
    }

    public function test_predikant_kan_andermans_beurt_niet_annuleren(): void
    {
        $predikant = User::factory()->create();
        $predikant->roles()->attach(Role::query()->firstOrCreate(['slug' => 'predikant'], ['naam' => 'Predikant']));
        $anderePredikant = User::factory()->create();
        $dienst = Dienst::factory()->create(['datum' => now()->addWeek()->format('Y-m-d')]);
        $spreekbeurt = Spreekbeurt::factory()->create([
            'spreker_id' => $anderePredikant->id,
            'dienst_id' => $dienst->id,
            'bevestigd' => 1,
        ]);

        $this->actingAs($predikant);
        session(['two_factor_verified' => true]);

        $this->postJson("/api/predikant/beurten/{$spreekbeurt->id}/annuleer", ['notitie' => 'Reden'])
            ->assertStatus(403);
    }

    public function test_annuleren_geblokkeerd_wanneer_uitgeschakeld(): void
    {
        Option::setValue(InstellingenService::KEY_BEURT_ANNULEREN_ENABLED, '0');

        $predikant = User::factory()->create();
        $predikant->roles()->attach(Role::query()->firstOrCreate(['slug' => 'predikant'], ['naam' => 'Predikant']));
        $dienst = Dienst::factory()->create(['datum' => now()->addWeek()->format('Y-m-d')]);
        $spreekbeurt = Spreekbeurt::factory()->create([
            'spreker_id' => $predikant->id,
            'dienst_id' => $dienst->id,
            'bevestigd' => 1,
        ]);

        $this->actingAs($predikant);
        session(['two_factor_verified' => true]);

        $this->postJson("/api/predikant/beurten/{$spreekbeurt->id}/annuleer", ['notitie' => 'Reden'])
            ->assertStatus(422);
    }

    public function test_annuleren_geblokkeerd_binnen_minimum_termijn(): void
    {
        Option::setValue(InstellingenService::KEY_BEURT_ANNULEREN_ENABLED, '1');
        Option::setValue(InstellingenService::KEY_BEURT_ANNULEREN_DAGEN, '7');

        $predikant = User::factory()->create();
        $predikant->roles()->attach(Role::query()->firstOrCreate(['slug' => 'predikant'], ['naam' => 'Predikant']));
        $dienst = Dienst::factory()->create(['datum' => now()->addDays(3)->format('Y-m-d')]);
        $spreekbeurt = Spreekbeurt::factory()->create([
            'spreker_id' => $predikant->id,
            'dienst_id' => $dienst->id,
            'bevestigd' => 1,
        ]);

        $this->actingAs($predikant);
        session(['two_factor_verified' => true]);

        $this->postJson("/api/predikant/beurten/{$spreekbeurt->id}/annuleer", ['notitie' => 'Reden'])
            ->assertStatus(422);
    }
}

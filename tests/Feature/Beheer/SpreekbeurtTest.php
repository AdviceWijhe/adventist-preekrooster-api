<?php

declare(strict_types=1);

namespace Tests\Feature\Beheer;

use App\Mail\PredikantUitvraagMail;
use App\Models\Dienst;
use App\Models\Functie;
use App\Models\Gemeente;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SpreekbeurtTest extends TestCase
{
    use RefreshDatabase;

    public function test_beheerder_kan_spreker_koppelen(): void
    {
        Mail::fake();

        $gemeente = Gemeente::factory()->create();
        $dienst = Dienst::factory()->create(['gemeente_id' => $gemeente->id]);
        $spreker = User::factory()->create();
        $spreker->roles()->attach(Role::query()->firstOrCreate(['slug' => 'predikant'], ['naam' => 'Predikant']));

        $beheerder = User::factory()->create();
        $beheerder->roles()->attach(Role::query()->firstOrCreate(['slug' => 'beheerder'], ['naam' => 'Beheerder']));
        $this->actingAs($beheerder);
        session(['two_factor_verified' => true]);

        $response = $this->postJson("/api/beheer/roosters/{$dienst->id}/sprekers", [
            'spreker_id' => $spreker->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('spreekbeurten', ['dienst_id' => $dienst->id, 'spreker_id' => $spreker->id]);
        Mail::assertSent(PredikantUitvraagMail::class, function (PredikantUitvraagMail $mail) use ($spreker): bool {
            return $mail->hasTo($spreker->email);
        });
    }

    public function test_beheerder_kan_spreker_met_functie_koppelen_zonder_predikant_rol(): void
    {
        Mail::fake();

        $gemeente = Gemeente::factory()->create();
        $dienst = Dienst::factory()->create(['gemeente_id' => $gemeente->id]);
        $spreker = User::factory()->create();
        $spreker->roles()->attach(Role::query()->firstOrCreate(['slug' => 'gebruiker'], ['naam' => 'Gebruiker']));
        $spreker->functies()->attach(Functie::query()->firstOrCreate(['slug' => 'predikant'], ['naam' => 'Predikant']));

        $beheerder = User::factory()->create();
        $beheerder->roles()->attach(Role::query()->firstOrCreate(['slug' => 'beheerder'], ['naam' => 'Beheerder']));
        $this->actingAs($beheerder);
        session(['two_factor_verified' => true]);

        $response = $this->postJson("/api/beheer/roosters/{$dienst->id}/sprekers", [
            'spreker_id' => $spreker->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('spreekbeurten', ['dienst_id' => $dienst->id, 'spreker_id' => $spreker->id]);
    }

    public function test_beheerder_kan_geen_gebruiker_zonder_prekfunctie_koppelen(): void
    {
        $gemeente = Gemeente::factory()->create();
        $dienst = Dienst::factory()->create(['gemeente_id' => $gemeente->id]);
        $sprekerZonderRol = User::factory()->create();

        $beheerder = User::factory()->create();
        $beheerder->roles()->attach(Role::query()->firstOrCreate(['slug' => 'beheerder'], ['naam' => 'Beheerder']));
        $this->actingAs($beheerder);
        session(['two_factor_verified' => true]);

        $response = $this->postJson("/api/beheer/roosters/{$dienst->id}/sprekers", [
            'spreker_id' => $sprekerZonderRol->id,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('spreker_id');
    }

    public function test_beheerder_kan_geen_inactieve_predikant_koppelen(): void
    {
        $gemeente = Gemeente::factory()->create();
        $dienst = Dienst::factory()->create(['gemeente_id' => $gemeente->id]);
        $inactievePredikant = User::factory()->create(['active' => false]);
        $inactievePredikant->roles()->attach(Role::query()->firstOrCreate(['slug' => 'predikant'], ['naam' => 'Predikant']));

        $beheerder = User::factory()->create();
        $beheerder->roles()->attach(Role::query()->firstOrCreate(['slug' => 'beheerder'], ['naam' => 'Beheerder']));
        $this->actingAs($beheerder);
        session(['two_factor_verified' => true]);

        $response = $this->postJson("/api/beheer/roosters/{$dienst->id}/sprekers", [
            'spreker_id' => $inactievePredikant->id,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('spreker_id');
    }

    public function test_geen_uitvraag_mail_wanneer_feature_uitgeschakeld(): void
    {
        Mail::fake();
        config(['mail.features.predikant_uitvraag' => false]);

        $gemeente = Gemeente::factory()->create();
        $dienst = Dienst::factory()->create(['gemeente_id' => $gemeente->id]);
        $spreker = User::factory()->create();
        $spreker->roles()->attach(Role::query()->firstOrCreate(['slug' => 'predikant'], ['naam' => 'Predikant']));

        $beheerder = User::factory()->create();
        $beheerder->roles()->attach(Role::query()->firstOrCreate(['slug' => 'beheerder'], ['naam' => 'Beheerder']));
        $this->actingAs($beheerder);
        session(['two_factor_verified' => true]);

        $this->postJson("/api/beheer/roosters/{$dienst->id}/sprekers", [
            'spreker_id' => $spreker->id,
        ])->assertStatus(201);

        Mail::assertNotSent(PredikantUitvraagMail::class);
    }
}

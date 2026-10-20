<?php

declare(strict_types=1);

namespace Tests\Feature\Beheer;

use App\Mail\BerichtNotificatieMail;
use App\Models\Bericht;
use App\Models\Gemeente;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BerichtTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->firstOrCreate(['slug' => 'admin'], ['naam' => 'Admin']));
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        return $user;
    }

    public function test_admin_kan_bericht_aanmaken_en_publiceren(): void
    {
        Mail::fake();
        $this->admin();

        $create = $this->postJson('/api/beheer/berichten', [
            'titel' => 'Algemene mededeling',
            'inhoud' => 'Volgende week is er avondmaal.',
            'doelgroep' => Bericht::DOELGROEP_ALLE,
            'kanaal' => Bericht::KANAAL_INTERN,
        ]);

        $create->assertCreated();
        $berichtId = $create->json('data.id');

        $publish = $this->postJson('/api/beheer/berichten/'.$berichtId.'/publiceren');
        $publish->assertOk();
        $this->assertNotNull($publish->json('data.gepubliceerd_op'));
    }

    public function test_beheerder_kan_geen_berichten_beheren(): void
    {
        Mail::fake();
        $beheerder = User::factory()->create();
        $beheerder->roles()->attach(Role::query()->firstOrCreate(['slug' => 'beheerder'], ['naam' => 'Beheerder']));
        $this->actingAs($beheerder);
        session(['two_factor_verified' => true]);

        $this->postJson('/api/beheer/berichten', [
            'titel' => 'Test',
            'inhoud' => 'Inhoud',
            'doelgroep' => Bericht::DOELGROEP_ALLE,
            'kanaal' => Bericht::KANAAL_INTERN,
        ])->assertForbidden();
    }

    public function test_publicatie_met_email_verstuurt_mail(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $ontvanger = User::factory()->create(['email' => 'predikant@example.com']);
        $ontvanger->roles()->attach(Role::query()->firstOrCreate(['slug' => 'predikant'], ['naam' => 'Predikant']));

        $bericht = Bericht::query()->create([
            'titel' => 'Belangrijk',
            'inhoud' => 'Lees dit.',
            'auteur_id' => $admin->id,
            'doelgroep' => Bericht::DOELGROEP_PREDIKANTEN,
            'kanaal' => Bericht::KANAAL_BEIDE,
        ]);

        $this->postJson('/api/beheer/berichten/'.$bericht->id.'/publiceren')->assertOk();

        Mail::assertQueued(BerichtNotificatieMail::class);
    }

    public function test_specifieke_doelgroep_vereist_gemeente_of_gebruiker(): void
    {
        Mail::fake();
        $this->admin();

        $this->postJson('/api/beheer/berichten', [
            'titel' => 'Specifiek',
            'inhoud' => 'Geen ontvangers gekozen.',
            'doelgroep' => Bericht::DOELGROEP_SPECIFIEK,
            'kanaal' => Bericht::KANAAL_INTERN,
        ])->assertUnprocessable()->assertJsonValidationErrors('doelgroep');
    }

    public function test_specifieke_doelgroep_mailt_gekozen_gemeente_en_gebruiker(): void
    {
        Mail::fake();
        $admin = $this->admin();

        $gemeente = Gemeente::factory()->create();
        $gemeenteLid = User::factory()->create(['email' => 'lid@example.com', 'gemeente_id' => $gemeente->id]);
        $losLid = User::factory()->create(['email' => 'los@example.com']);
        $buitenstaander = User::factory()->create(['email' => 'buiten@example.com']);

        $create = $this->postJson('/api/beheer/berichten', [
            'titel' => 'Voor jullie',
            'inhoud' => 'Specifieke groep.',
            'doelgroep' => Bericht::DOELGROEP_SPECIFIEK,
            'gemeente_ids' => [$gemeente->id],
            'gebruiker_ids' => [$losLid->id],
            'kanaal' => Bericht::KANAAL_EMAIL,
        ]);
        $create->assertCreated();
        $create->assertJsonPath('data.gemeente_ids', [$gemeente->id]);
        $create->assertJsonPath('data.gebruiker_ids', [$losLid->id]);

        $berichtId = $create->json('data.id');
        $this->postJson('/api/beheer/berichten/'.$berichtId.'/publiceren')->assertOk();

        Mail::assertQueued(BerichtNotificatieMail::class, function ($mail) use ($gemeenteLid) {
            return $mail->hasTo($gemeenteLid->email);
        });
        Mail::assertQueued(BerichtNotificatieMail::class, function ($mail) use ($losLid) {
            return $mail->hasTo($losLid->email);
        });
        Mail::assertNotQueued(BerichtNotificatieMail::class, function ($mail) use ($buitenstaander) {
            return $mail->hasTo($buitenstaander->email);
        });

        $this->actingAs($gemeenteLid);
        session(['two_factor_verified' => true]);
        $inboxGemeenteLid = $this->getJson('/api/berichten/inbox');
        $inboxGemeenteLid->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($buitenstaander);
        session(['two_factor_verified' => true]);
        $inboxBuitenstaander = $this->getJson('/api/berichten/inbox');
        $inboxBuitenstaander->assertOk()->assertJsonCount(0, 'data');
    }
}

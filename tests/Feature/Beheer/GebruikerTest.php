<?php

declare(strict_types=1);

namespace Tests\Feature\Beheer;

use App\Mail\WelkomGebruikerMail;
use App\Models\Dienst;
use App\Models\Functie;
use App\Models\Gemeente;
use App\Models\Option;
use App\Models\Role;
use App\Models\Spreekbeurt;
use App\Models\User;
use App\Models\UserBeschikbaarheid;
use App\Services\Instellingen\InstellingenService;
use App\Services\RoosterAutorisatieService;
use Database\Seeders\FunctieSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class GebruikerTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $adminRole = Role::query()->firstOrCreate(
            ['slug' => 'admin'],
            ['naam' => 'Administrator']
        );
        Role::query()->firstOrCreate(['slug' => 'beheerder'], ['naam' => 'Beheerder']);
        Role::query()->firstOrCreate(['slug' => 'predikant'], ['naam' => 'Predikant']);
        Role::query()->firstOrCreate(['slug' => 'gebruiker'], ['naam' => 'Gebruiker']);
        $user->roles()->attach($adminRole);

        return $user;
    }

    private function beheerderUser(): User
    {
        Role::query()->firstOrCreate(['slug' => 'admin'], ['naam' => 'Administrator']);
        Role::query()->firstOrCreate(['slug' => 'beheerder'], ['naam' => 'Beheerder']);
        Role::query()->firstOrCreate(['slug' => 'gebruiker'], ['naam' => 'Gebruiker']);

        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', 'beheerder')->firstOrFail());

        return $user;
    }

    private function gebruikerPayload(array $overrides = []): array
    {
        return array_merge([
            'voornaam' => 'Jan',
            'achternaam' => 'Jansen',
            'email' => 'jan-'.uniqid('', true).'@adventist.nl',
            'geslacht' => 'm',
            'taal' => 'nl',
            'role' => 'gebruiker',
        ], $overrides);
    }

    public function test_admin_kan_gebruikers_opvragen(): void
    {
        User::factory()->count(3)->create();
        $user = $this->adminUser();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $response = $this->getJson('/api/beheer/gebruikers');

        $response->assertStatus(200)->assertJsonStructure([
            'data' => [['id', 'voornaam', 'achternaam', 'email']],
        ]);
    }

    public function test_admin_kan_gebruikers_zoeken_op_naam_en_email(): void
    {
        User::factory()->create([
            'voornaam' => 'Pieter',
            'achternaam' => 'Zoekveld',
            'email' => 'pieter@adventist.nl',
        ]);
        User::factory()->create([
            'voornaam' => 'Klaas',
            'achternaam' => 'Anders',
            'email' => 'klaas@voorbeeld.nl',
        ]);

        $user = $this->adminUser();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $response = $this->getJson('/api/beheer/gebruikers?search=pieter');
        $response->assertStatus(200)->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.email', 'pieter@adventist.nl');

        $responseGeenMatch = $this->getJson('/api/beheer/gebruikers?search=onvindbaar');
        $responseGeenMatch->assertStatus(200)->assertJsonCount(0, 'data');
    }

    public function test_admin_kan_gebruiker_aanmaken(): void
    {
        Mail::fake();
        $user = $this->adminUser();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $response = $this->postJson('/api/beheer/gebruikers', [
            'voornaam' => 'Jan',
            'achternaam' => 'Jansen',
            'email' => 'jan@adventist.nl',
            'geslacht' => 'm',
            'taal' => 'nl',
            'role' => 'predikant',
        ]);

        $response->assertStatus(201)->assertJsonPath('data.email', 'jan@adventist.nl');
        $this->assertDatabaseHas('users', ['email' => 'jan@adventist.nl']);
        Mail::assertSent(WelkomGebruikerMail::class, function (WelkomGebruikerMail $mail): bool {
            return $mail->hasTo('jan@adventist.nl')
                && str_contains($mail->setPasswordUrl, '/wachtwoord-aanmaken/')
                && ! str_contains($mail->setPasswordUrl, 'email=');
        });
    }

    public function test_email_moet_uniek_zijn(): void
    {
        User::factory()->create(['email' => 'bestaand@adventist.nl']);
        $user = $this->adminUser();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $response = $this->postJson('/api/beheer/gebruikers', [
            'voornaam' => 'Jan',
            'achternaam' => 'Jansen',
            'email' => 'bestaand@adventist.nl',
            'password' => 'Welkom123!',
            'geslacht' => 'm',
            'taal' => 'nl',
            'role' => 'predikant',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_admin_kan_gebruiker_verwijderen_zonder_koppelingen(): void
    {
        $admin = $this->adminUser();
        $doel = User::factory()->create();
        $this->actingAs($admin);
        session(['two_factor_verified' => true]);

        $response = $this->deleteJson('/api/beheer/gebruikers/'.$doel->id);

        $response->assertStatus(204);
        $this->assertDatabaseMissing('users', ['id' => $doel->id]);
    }

    public function test_beheerder_kan_eigen_account_niet_verwijderen(): void
    {
        $admin = $this->adminUser();
        $this->actingAs($admin);
        session(['two_factor_verified' => true]);

        $response = $this->deleteJson('/api/beheer/gebruikers/'.$admin->id);

        $response->assertStatus(403);
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_gebruiker_verwijderen_geblokkeerd_door_spreekbeurt_als_spreker(): void
    {
        $admin = $this->adminUser();
        $spreker = User::factory()->create();
        $dienst = Dienst::factory()->create();
        Spreekbeurt::factory()->create([
            'dienst_id' => $dienst->id,
            'spreker_id' => $spreker->id,
        ]);
        $this->actingAs($admin);
        session(['two_factor_verified' => true]);

        $response = $this->deleteJson('/api/beheer/gebruikers/'.$spreker->id);

        $response->assertStatus(409);
        $this->assertDatabaseHas('users', ['id' => $spreker->id]);
    }

    public function test_gebruiker_verwijderen_geblokkeerd_als_gemeente_predikant(): void
    {
        $admin = $this->adminUser();
        $predikant = User::factory()->create();
        Gemeente::factory()->create(['predikant_id' => $predikant->id]);
        $this->actingAs($admin);
        session(['two_factor_verified' => true]);

        $response = $this->deleteJson('/api/beheer/gebruikers/'.$predikant->id);

        $response->assertStatus(409);
        $this->assertDatabaseHas('users', ['id' => $predikant->id]);
    }

    public function test_admin_kan_functies_en_gemeentes_koppelen(): void
    {
        Mail::fake();
        $this->seed(FunctieSeeder::class);
        Role::query()->firstOrCreate(['slug' => 'gebruiker'], ['naam' => 'Gebruiker']);
        $admin = $this->adminUser();
        $g1 = Gemeente::factory()->create();
        $g2 = Gemeente::factory()->create();
        $this->actingAs($admin);
        session(['two_factor_verified' => true]);

        $response = $this->postJson('/api/beheer/gebruikers', [
            'voornaam' => 'Multi',
            'achternaam' => 'Functie',
            'email' => 'multi@adventist.nl',
            'password' => 'Welkom123!',
            'geslacht' => 'm',
            'taal' => 'nl',
            'role' => 'gebruiker',
            'functies' => ['predikant', 'spreker'],
            'gemeente_ids' => [$g1->id, $g2->id],
        ]);

        $response->assertStatus(201);
        $user = User::query()->where('email', 'multi@adventist.nl')->firstOrFail();
        $this->assertCount(2, $user->functies);
        $this->assertCount(2, $user->gemeentes);
        $this->assertSame($g1->id, $user->gemeente_id);
        $this->assertSame($g1->id, (int) $user->roles()->first()->pivot->gemeente_id);
        $this->assertSame($user->id, $g1->fresh()->predikant_id);
        $this->assertSame($user->id, $g2->fresh()->predikant_id);
    }

    public function test_predikant_functie_en_gemeente_zetten_predikant_id_voor_scope(): void
    {
        Mail::fake();
        $this->seed(FunctieSeeder::class);
        Role::query()->firstOrCreate(['slug' => 'gebruiker'], ['naam' => 'Gebruiker']);
        $admin = $this->adminUser();
        $gemeente = Gemeente::factory()->create(['predikant_id' => null]);
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', 'gebruiker')->firstOrFail()->id);
        $this->actingAs($admin);
        session(['two_factor_verified' => true]);

        $this->putJson('/api/beheer/gebruikers/'.$user->id, [
            'voornaam' => $user->voornaam,
            'achternaam' => $user->achternaam,
            'email' => $user->email,
            'geslacht' => 'm',
            'taal' => 'nl',
            'role' => 'gebruiker',
            'functies' => ['predikant'],
            'gemeente_ids' => [$gemeente->id],
        ])->assertOk();

        $this->assertSame($user->id, $gemeente->fresh()->predikant_id);
        $this->assertTrue(
            app(RoosterAutorisatieService::class)->heeftRoosterOfGemeenteScope($user->fresh())
        );
    }

    public function test_contactpersoon_zet_contactpersoon_id_en_wist_spreekniveau(): void
    {
        Mail::fake();
        $this->seed(FunctieSeeder::class);
        Role::query()->firstOrCreate(['slug' => 'gebruiker'], ['naam' => 'Gebruiker']);
        $admin = $this->adminUser();
        $gemeente = Gemeente::factory()->create(['contactpersoon_id' => null, 'predikant_id' => null]);
        $user = User::factory()->create(['spreekniveau' => 'lekenprediker_licentie_2']);
        $user->roles()->attach(Role::query()->where('slug', 'gebruiker')->firstOrFail()->id);
        $this->actingAs($admin);
        session(['two_factor_verified' => true]);

        $this->putJson('/api/beheer/gebruikers/'.$user->id, [
            'voornaam' => $user->voornaam,
            'achternaam' => $user->achternaam,
            'email' => $user->email,
            'geslacht' => 'm',
            'taal' => 'nl',
            'role' => 'gebruiker',
            'functies' => ['contactpersoon'],
            'gemeente_ids' => [$gemeente->id],
            'spreekniveau' => 'lekenprediker_licentie_2',
        ])->assertOk();

        $user->refresh();
        $this->assertSame($user->id, $gemeente->fresh()->contactpersoon_id);
        $this->assertNull($user->spreekniveau);
        $this->assertTrue($user->hasFunctie('contactpersoon'));
        $this->assertTrue(
            app(RoosterAutorisatieService::class)->heeftRoosterOfGemeenteScope($user)
        );
    }

    public function test_profielmail_fout_blokkeert_contactpersoon_update_niet(): void
    {
        $this->seed(FunctieSeeder::class);
        Role::query()->firstOrCreate(['slug' => 'gebruiker'], ['naam' => 'Gebruiker']);
        $admin = $this->adminUser();
        $gemeente = Gemeente::factory()->create(['contactpersoon_id' => null]);
        $user = User::factory()->create([
            'email' => 'mailfout@adventist.nl',
            'spreekniveau' => 'lekenprediker_licentie_2',
        ]);
        $user->roles()->attach(Role::query()->where('slug', 'gebruiker')->firstOrFail()->id);
        $this->actingAs($admin);
        session(['two_factor_verified' => true]);

        Event::listen(
            MessageSending::class,
            static fn () => throw new \RuntimeException('SMTP down')
        );

        $this->putJson('/api/beheer/gebruikers/'.$user->id, [
            'voornaam' => $user->voornaam,
            'achternaam' => $user->achternaam,
            'email' => $user->email,
            'geslacht' => 'm',
            'taal' => 'nl',
            'role' => 'gebruiker',
            'functies' => ['contactpersoon'],
            'gemeente_ids' => [$gemeente->id],
            'spreekniveau' => 'lekenprediker_licentie_2',
        ])->assertOk();

        $this->assertNull($user->fresh()->spreekniveau);
        $this->assertSame($user->id, $gemeente->fresh()->contactpersoon_id);
    }

    public function test_admin_kan_spreekniveau_zetten(): void
    {
        Mail::fake();
        Role::query()->firstOrCreate(['slug' => 'gebruiker'], ['naam' => 'Gebruiker']);
        $admin = $this->adminUser();
        $this->actingAs($admin);
        session(['two_factor_verified' => true]);

        $response = $this->postJson('/api/beheer/gebruikers', [
            'voornaam' => 'Niveau',
            'achternaam' => 'Test',
            'email' => 'niveau@adventist.nl',
            'password' => 'Welkom123!',
            'geslacht' => 'm',
            'taal' => 'nl',
            'role' => 'gebruiker',
            'spreekniveau' => 'lekenprediker_licentie_2',
        ]);

        $response->assertStatus(201);
        $this->assertSame(
            'lekenprediker_licentie_2',
            User::query()->where('email', 'niveau@adventist.nl')->value('spreekniveau')
        );
    }

    public function test_contactpersonen_met_avg_consent_overzicht(): void
    {
        Option::setValue(InstellingenService::KEY_AVG_ENABLED, '1');
        $this->seed(FunctieSeeder::class);
        $admin = $this->adminUser();
        $this->actingAs($admin);
        session(['two_factor_verified' => true]);

        $contactFunctie = Functie::query()->where('slug', 'contactpersoon')->firstOrFail();

        $metConsent = User::factory()->create([
            'voornaam' => 'Anna',
            'achternaam' => 'Contact',
            'email' => 'anna@example.test',
            'telefoonnummer' => '0612345678',
            'avg_consent_at' => now(),
        ]);
        $metConsent->functies()->attach($contactFunctie->id);

        $zonderConsent = User::factory()->create([
            'voornaam' => 'Bert',
            'achternaam' => 'Contact',
            'avg_consent_at' => null,
        ]);
        $zonderConsent->functies()->attach($contactFunctie->id);

        $predikant = User::factory()->create([
            'voornaam' => 'Karel',
            'achternaam' => 'Prediker',
            'avg_consent_at' => now(),
            'telefoonnummer' => '0698765432',
        ]);
        $predikant->functies()->attach(Functie::query()->where('slug', 'predikant')->firstOrFail()->id);

        $inactievePredikant = User::factory()->create([
            'voornaam' => 'Ina',
            'achternaam' => 'Actief',
            'email' => 'ina-inactief@example.test',
            'avg_consent_at' => now(),
            'telefoonnummer' => '0611111111',
            'active' => false,
        ]);
        $inactievePredikant->functies()->attach(Functie::query()->where('slug', 'predikant')->firstOrFail()->id);

        $this->getJson('/api/beheer/gebruikers/contactpersonen')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $emails = collect($this->getJson('/api/beheer/gebruikers/contactpersonen')->json('data'))->pluck('email');
        $this->assertTrue($emails->contains('anna@example.test'));
        $this->assertTrue($emails->contains($predikant->email));
        $this->assertFalse($emails->contains('ina-inactief@example.test'));

        $this->getJson('/api/beheer/gebruikers/contactpersonen?search=Anna')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/beheer/gebruikers/contactpersonen?search=Bert')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_spreekniveau_moet_geldig_zijn(): void
    {
        Mail::fake();
        Role::query()->firstOrCreate(['slug' => 'gebruiker'], ['naam' => 'Gebruiker']);
        $admin = $this->adminUser();
        $this->actingAs($admin);
        session(['two_factor_verified' => true]);

        $this->postJson('/api/beheer/gebruikers', [
            'voornaam' => 'Niveau',
            'achternaam' => 'Test',
            'email' => 'niveau2@adventist.nl',
            'password' => 'Welkom123!',
            'geslacht' => 'm',
            'taal' => 'nl',
            'role' => 'gebruiker',
            'spreekniveau' => 'onbestaand',
        ])->assertStatus(422)->assertJsonValidationErrors(['spreekniveau']);
    }

    public function test_alleen_admin_kan_statistieken_toegang_zetten(): void
    {
        Mail::fake();
        Role::query()->firstOrCreate(['slug' => 'gebruiker'], ['naam' => 'Gebruiker']);
        $doel = User::factory()->create(['statistieken_toegang' => false]);

        $beheerder = User::factory()->create();
        $beheerder->roles()->attach(Role::query()->firstOrCreate(['slug' => 'beheerder'], ['naam' => 'Beheerder']));
        $this->actingAs($beheerder);
        session(['two_factor_verified' => true]);

        $payload = [
            'voornaam' => $doel->voornaam,
            'achternaam' => $doel->achternaam,
            'email' => $doel->email,
            'geslacht' => $doel->geslacht,
            'taal' => $doel->taal,
            'role' => 'gebruiker',
            'statistieken_toegang' => true,
        ];

        $this->putJson('/api/beheer/gebruikers/'.$doel->id, $payload)->assertStatus(200);
        $this->assertFalse((bool) $doel->fresh()->statistieken_toegang);

        $admin = $this->adminUser();
        $this->actingAs($admin);
        session(['two_factor_verified' => true]);

        $this->putJson('/api/beheer/gebruikers/'.$doel->id, $payload)->assertStatus(200);
        $this->assertTrue((bool) $doel->fresh()->statistieken_toegang);
    }

    public function test_admin_kan_zichzelf_niet_degraderen_naar_beheerder(): void
    {
        $admin = $this->adminUser();
        $this->actingAs($admin);
        session(['two_factor_verified' => true]);

        $payload = [
            'voornaam' => $admin->voornaam,
            'achternaam' => $admin->achternaam,
            'email' => $admin->email,
            'geslacht' => $admin->geslacht,
            'taal' => $admin->taal,
            'role' => 'beheerder',
        ];

        $this->putJson('/api/beheer/gebruikers/'.$admin->id, $payload)
            ->assertStatus(403)
            ->assertJsonPath('message', __('api.beheer.cannot_demote_last_admin'));

        $this->assertTrue($admin->fresh()->isAdmin());
    }

    public function test_beheerder_kan_geen_admin_of_beheerder_aanmaken(): void
    {
        Mail::fake();
        $beheerder = $this->beheerderUser();
        $this->actingAs($beheerder);
        session(['two_factor_verified' => true]);

        $this->postJson('/api/beheer/gebruikers', $this->gebruikerPayload(['role' => 'beheerder']))
            ->assertStatus(403)
            ->assertJsonPath('message', __('api.beheer.role_change_beheerder_admin_only'));

        $this->postJson('/api/beheer/gebruikers', $this->gebruikerPayload(['role' => 'admin']))
            ->assertStatus(403)
            ->assertJsonPath('message', __('api.beheer.role_assign_admin_forbidden'));
    }

    public function test_beheerder_kan_rol_van_admin_niet_wijzigen(): void
    {
        $beheerder = $this->beheerderUser();
        $admin = $this->adminUser();
        $this->actingAs($beheerder);
        session(['two_factor_verified' => true]);

        $payload = [
            'voornaam' => $admin->voornaam,
            'achternaam' => $admin->achternaam,
            'email' => $admin->email,
            'geslacht' => $admin->geslacht,
            'taal' => $admin->taal,
            'role' => 'gebruiker',
        ];

        $this->putJson('/api/beheer/gebruikers/'.$admin->id, $payload)
            ->assertStatus(403)
            ->assertJsonPath('message', __('api.beheer.admin_account_admin_only'));

        $this->assertTrue($admin->fresh()->isAdmin());
    }

    public function test_tweede_admin_kan_eerste_admin_degraderen(): void
    {
        $admin = $this->adminUser();
        $tweedeAdmin = User::factory()->create();
        $tweedeAdmin->roles()->attach(Role::query()->where('slug', 'admin')->firstOrFail());

        $this->actingAs($tweedeAdmin);
        session(['two_factor_verified' => true]);

        $payload = [
            'voornaam' => $admin->voornaam,
            'achternaam' => $admin->achternaam,
            'email' => $admin->email,
            'geslacht' => $admin->geslacht,
            'taal' => $admin->taal,
            'role' => 'beheerder',
        ];

        $this->putJson('/api/beheer/gebruikers/'.$admin->id, $payload)->assertStatus(200);
        $this->assertFalse($admin->fresh()->isAdmin());
        $this->assertTrue($admin->fresh()->hasRole('beheerder'));
    }

    public function test_beheerder_kan_gebruiker_niet_promoveren_tot_admin(): void
    {
        $beheerder = $this->beheerderUser();
        $gebruiker = User::factory()->create();
        $gebruiker->roles()->attach(Role::query()->where('slug', 'gebruiker')->firstOrFail());

        $this->actingAs($beheerder);
        session(['two_factor_verified' => true]);

        $payload = [
            'voornaam' => $gebruiker->voornaam,
            'achternaam' => $gebruiker->achternaam,
            'email' => $gebruiker->email,
            'geslacht' => $gebruiker->geslacht,
            'taal' => $gebruiker->taal,
            'role' => 'admin',
        ];

        $this->putJson('/api/beheer/gebruikers/'.$gebruiker->id, $payload)
            ->assertStatus(403)
            ->assertJsonPath('message', __('api.beheer.role_assign_admin_forbidden'));

        $this->assertFalse($gebruiker->fresh()->isAdmin());
    }

    public function test_beheerder_kan_admin_niet_bewerken(): void
    {
        $beheerder = $this->beheerderUser();
        $admin = $this->adminUser();

        $this->actingAs($beheerder);
        session(['two_factor_verified' => true]);

        $payload = [
            'voornaam' => 'Bijgewerkt',
            'achternaam' => $admin->achternaam,
            'email' => $admin->email,
            'geslacht' => $admin->geslacht,
            'taal' => $admin->taal,
            'role' => 'admin',
        ];

        $this->putJson('/api/beheer/gebruikers/'.$admin->id, $payload)
            ->assertStatus(403)
            ->assertJsonPath('message', __('api.beheer.admin_account_admin_only'));

        $this->assertSame($admin->fresh()->voornaam, $admin->voornaam);
    }

    public function test_beheerder_kan_admin_niet_verwijderen(): void
    {
        $beheerder = $this->beheerderUser();
        $admin = $this->adminUser();

        $this->actingAs($beheerder);
        session(['two_factor_verified' => true]);

        $this->deleteJson('/api/beheer/gebruikers/'.$admin->id)
            ->assertStatus(403)
            ->assertJsonPath('message', __('api.beheer.admin_account_admin_only'));

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_admin_kan_uitnodiging_opnieuw_versturen(): void
    {
        Mail::fake();
        $admin = $this->adminUser();
        $gebruiker = User::factory()->create(['email' => 'uitnodiging@adventist.nl']);

        $this->actingAs($admin);
        session(['two_factor_verified' => true]);

        $response = $this->postJson('/api/beheer/gebruikers/'.$gebruiker->id.'/uitnodiging');

        $response->assertStatus(200)->assertJsonPath('message', __('api.beheer.uitnodiging_verstuurd'));
        Mail::assertSent(WelkomGebruikerMail::class, function (WelkomGebruikerMail $mail): bool {
            return $mail->hasTo('uitnodiging@adventist.nl')
                && str_contains($mail->setPasswordUrl, '/wachtwoord-aanmaken/')
                && ! str_contains($mail->setPasswordUrl, 'email=');
        });
    }

    public function test_admin_wachtwoordwijziging_invalideert_sessies(): void
    {
        Mail::fake();
        config(['session.driver' => 'database']);

        $admin = $this->adminUser();
        $doel = User::factory()->create([
            'remember_token' => 'oud-token-1234567890abcdefghij',
        ]);
        Role::query()->firstOrCreate(['slug' => 'gebruiker'], ['naam' => 'Gebruiker']);

        DB::table('sessions')->insert([
            'id' => 'sessie-doel-1',
            'user_id' => $doel->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => 'payload',
            'last_activity' => time(),
        ]);

        $this->actingAs($admin);
        session(['two_factor_verified' => true]);

        $this->putJson('/api/beheer/gebruikers/'.$doel->id, $this->gebruikerPayload([
            'email' => $doel->email,
            'voornaam' => $doel->voornaam,
            'achternaam' => $doel->achternaam,
            'password' => 'NieuwWachtwoord123!',
            'role' => 'gebruiker',
            'active' => true,
        ]))->assertOk();

        $this->assertDatabaseMissing('sessions', ['user_id' => $doel->id]);
        $this->assertNotSame('oud-token-1234567890abcdefghij', $doel->fresh()->remember_token);
    }

    public function test_deactiveren_gebruiker_invalideert_sessies(): void
    {
        Mail::fake();
        config(['session.driver' => 'database']);

        $admin = $this->adminUser();
        $doel = User::factory()->create(['active' => true]);
        Role::query()->firstOrCreate(['slug' => 'gebruiker'], ['naam' => 'Gebruiker']);

        DB::table('sessions')->insert([
            'id' => 'sessie-doel-2',
            'user_id' => $doel->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => 'payload',
            'last_activity' => time(),
        ]);

        $this->actingAs($admin);
        session(['two_factor_verified' => true]);

        $this->putJson('/api/beheer/gebruikers/'.$doel->id, $this->gebruikerPayload([
            'email' => $doel->email,
            'voornaam' => $doel->voornaam,
            'achternaam' => $doel->achternaam,
            'role' => 'gebruiker',
            'active' => false,
        ]))->assertOk();

        $this->assertFalse($doel->fresh()->active);
        $this->assertDatabaseMissing('sessions', ['user_id' => $doel->id]);
    }

    public function test_beheerder_kan_andere_beheerder_niet_deactiveren(): void
    {
        Mail::fake();
        $actor = $this->beheerderUser();
        $peer = $this->beheerderUser();

        $this->actingAs($actor);
        session(['two_factor_verified' => true]);

        $this->putJson('/api/beheer/gebruikers/'.$peer->id, $this->gebruikerPayload([
            'email' => $peer->email,
            'voornaam' => $peer->voornaam,
            'achternaam' => $peer->achternaam,
            'role' => 'beheerder',
            'active' => false,
        ]))
            ->assertStatus(403)
            ->assertJsonPath('message', __('api.beheer.beheerder_sensitive_admin_only'));

        $this->assertTrue($peer->fresh()->active);
    }

    public function test_beheerder_kan_andere_beheerder_wachtwoord_niet_wijzigen(): void
    {
        Mail::fake();
        $actor = $this->beheerderUser();
        $peer = $this->beheerderUser();
        $hashVoorheen = $peer->password;

        $this->actingAs($actor);
        session(['two_factor_verified' => true]);

        $this->putJson('/api/beheer/gebruikers/'.$peer->id, $this->gebruikerPayload([
            'email' => $peer->email,
            'voornaam' => $peer->voornaam,
            'achternaam' => $peer->achternaam,
            'role' => 'beheerder',
            'password' => 'NieuwWachtwoord123!',
            'active' => true,
        ]))
            ->assertStatus(403)
            ->assertJsonPath('message', __('api.beheer.beheerder_sensitive_admin_only'));

        $this->assertSame($hashVoorheen, $peer->fresh()->password);
    }

    public function test_admin_kan_beheerder_deactiveren_en_wachtwoord_wijzigen(): void
    {
        Mail::fake();
        $admin = $this->adminUser();
        $peer = $this->beheerderUser();

        $this->actingAs($admin);
        session(['two_factor_verified' => true]);

        $this->putJson('/api/beheer/gebruikers/'.$peer->id, $this->gebruikerPayload([
            'email' => $peer->email,
            'voornaam' => $peer->voornaam,
            'achternaam' => $peer->achternaam,
            'role' => 'beheerder',
            'password' => 'NieuwWachtwoord123!',
            'active' => false,
        ]))->assertOk();

        $this->assertFalse($peer->fresh()->active);
    }

    public function test_beheerder_kan_andere_beheerder_naam_wijzigen(): void
    {
        Mail::fake();
        $actor = $this->beheerderUser();
        $peer = $this->beheerderUser();

        $this->actingAs($actor);
        session(['two_factor_verified' => true]);

        $this->putJson('/api/beheer/gebruikers/'.$peer->id, $this->gebruikerPayload([
            'email' => $peer->email,
            'voornaam' => 'Nieuwe',
            'achternaam' => $peer->achternaam,
            'role' => 'beheerder',
            'active' => true,
        ]))->assertOk();

        $this->assertSame('Nieuwe', $peer->fresh()->voornaam);
    }

    public function test_beheerder_kan_admin_niet_tonen(): void
    {
        $beheerder = $this->beheerderUser();
        $admin = $this->adminUser();

        $this->actingAs($beheerder);
        session(['two_factor_verified' => true]);

        $this->getJson('/api/beheer/gebruikers/'.$admin->id)
            ->assertStatus(403)
            ->assertJsonPath('message', __('api.beheer.admin_account_admin_only'));
    }

    public function test_admin_kan_admin_tonen(): void
    {
        $admin = $this->adminUser();
        $andereAdmin = $this->adminUser();

        $this->actingAs($admin);
        session(['two_factor_verified' => true]);

        $this->getJson('/api/beheer/gebruikers/'.$andereAdmin->id)
            ->assertOk()
            ->assertJsonPath('data.id', $andereAdmin->id);
    }

    public function test_beheerder_kan_admin_niet_activeren_via_inactieve_accounts(): void
    {
        Mail::fake();
        $beheerder = $this->beheerderUser();
        $admin = $this->adminUser();
        $admin->update(['active' => false]);

        $this->actingAs($beheerder);
        session(['two_factor_verified' => true]);

        $this->postJson('/api/beheer/inactieve-accounts/'.$admin->id.'/activeer')
            ->assertStatus(403)
            ->assertJsonPath('message', __('api.beheer.admin_account_admin_only'));

        $this->assertFalse($admin->fresh()->active);
    }

    public function test_admin_ziet_rooster_en_verlof_van_gebruiker(): void
    {
        $admin = $this->adminUser();
        $spreker = User::factory()->create();
        $andere = User::factory()->create();

        $gemeenteVroeg = Gemeente::factory()->create(['naam' => 'Zwolle']);
        $gemeenteLaat = Gemeente::factory()->create(['naam' => 'Wijhe']);
        $dienstVroeg = Dienst::factory()->create([
            'datum' => '2026-03-07',
            'gemeente_id' => $gemeenteVroeg->id,
            'type' => 'sabbatschool',
            'dienstwijze' => 'online',
        ]);
        $dienstLaat = Dienst::factory()->create([
            'datum' => '2026-11-14',
            'gemeente_id' => $gemeenteLaat->id,
            'type' => 'dienst',
            'dienstwijze' => 'fysiek',
        ]);
        Spreekbeurt::factory()->create([
            'spreker_id' => $spreker->id,
            'dienst_id' => $dienstLaat->id,
            'bevestigd' => 1,
        ]);
        Spreekbeurt::factory()->create([
            'spreker_id' => $spreker->id,
            'dienst_id' => $dienstVroeg->id,
            'bevestigd' => null,
        ]);
        Spreekbeurt::factory()->create([
            'spreker_id' => $andere->id,
            'dienst_id' => $dienstLaat->id,
            'bevestigd' => 0,
        ]);

        UserBeschikbaarheid::factory()->create([
            'user_id' => $spreker->id,
            'datum_van' => '2026-12-20',
            'datum_tot' => '2026-12-27',
            'opmerking' => 'Kerstvakantie',
        ]);
        UserBeschikbaarheid::factory()->create([
            'user_id' => $spreker->id,
            'datum_van' => '2026-04-01',
            'datum_tot' => '2026-04-08',
            'opmerking' => null,
        ]);
        UserBeschikbaarheid::factory()->create([
            'user_id' => $andere->id,
            'datum_van' => '2026-01-01',
            'datum_tot' => '2026-01-02',
            'opmerking' => 'Niet van deze gebruiker',
        ]);

        $this->actingAs($admin);
        session(['two_factor_verified' => true]);

        $response = $this->getJson('/api/beheer/gebruikers/'.$spreker->id.'/planning');

        $response->assertOk()
            ->assertJsonCount(2, 'data.spreekbeurten')
            ->assertJsonCount(2, 'data.beschikbaarheid')
            ->assertJsonPath('data.spreekbeurten.0.datum', '2026-03-07')
            ->assertJsonPath('data.spreekbeurten.0.gemeente', 'Zwolle')
            ->assertJsonPath('data.spreekbeurten.0.type', 'sabbatschool')
            ->assertJsonPath('data.spreekbeurten.0.dienstwijze', 'online')
            ->assertJsonPath('data.spreekbeurten.0.bevestigd', null)
            ->assertJsonPath('data.spreekbeurten.1.datum', '2026-11-14')
            ->assertJsonPath('data.spreekbeurten.1.gemeente', 'Wijhe')
            ->assertJsonPath('data.spreekbeurten.1.bevestigd', 1)
            ->assertJsonPath('data.beschikbaarheid.0.datum_van', '2026-04-01')
            ->assertJsonPath('data.beschikbaarheid.0.datum_tot', '2026-04-08')
            ->assertJsonPath('data.beschikbaarheid.0.opmerking', null)
            ->assertJsonPath('data.beschikbaarheid.1.opmerking', 'Kerstvakantie');
    }

    public function test_planning_is_leeg_zonder_beurten_of_verlof(): void
    {
        $admin = $this->adminUser();
        $spreker = User::factory()->create();

        $this->actingAs($admin);
        session(['two_factor_verified' => true]);

        $this->getJson('/api/beheer/gebruikers/'.$spreker->id.'/planning')
            ->assertOk()
            ->assertJsonPath('data.spreekbeurten', [])
            ->assertJsonPath('data.beschikbaarheid', []);
    }

    public function test_predikant_mag_planning_van_ander_niet_zien(): void
    {
        $predikant = User::factory()->create();
        $predikant->roles()->attach(Role::query()->firstOrCreate(['slug' => 'predikant'], ['naam' => 'Predikant']));
        $ander = User::factory()->create();

        $this->actingAs($predikant);
        session(['two_factor_verified' => true]);

        $this->getJson('/api/beheer/gebruikers/'.$ander->id.'/planning')->assertStatus(403);
    }

    public function test_beheerder_mag_planning_van_admin_niet_zien(): void
    {
        $beheerder = $this->beheerderUser();
        $admin = $this->adminUser();

        $this->actingAs($beheerder);
        session(['two_factor_verified' => true]);

        $this->getJson('/api/beheer/gebruikers/'.$admin->id.'/planning')
            ->assertStatus(403)
            ->assertJsonPath('message', __('api.beheer.admin_account_admin_only'));
    }

    public function test_beheerder_ziet_planning_van_gewone_gebruiker(): void
    {
        $beheerder = $this->beheerderUser();
        $spreker = User::factory()->create();
        $dienst = Dienst::factory()->create(['datum' => '2026-06-06', 'type' => 'dienst']);
        Spreekbeurt::factory()->create([
            'spreker_id' => $spreker->id,
            'dienst_id' => $dienst->id,
            'bevestigd' => 0,
        ]);

        $this->actingAs($beheerder);
        session(['two_factor_verified' => true]);

        $this->getJson('/api/beheer/gebruikers/'.$spreker->id.'/planning')
            ->assertOk()
            ->assertJsonPath('data.spreekbeurten.0.bevestigd', 0)
            ->assertJsonPath('data.spreekbeurten.0.datum', '2026-06-06');
    }
}

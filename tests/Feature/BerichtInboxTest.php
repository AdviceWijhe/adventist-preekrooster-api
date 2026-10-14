<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Bericht;
use App\Models\Role;
use App\Models\User;
use App\Services\Bericht\BerichtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BerichtInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_gebruiker_ziet_gepubliceerde_berichten_in_inbox(): void
    {
        $admin = User::factory()->create();
        $predikant = User::factory()->create();
        $predikant->roles()->attach(Role::query()->firstOrCreate(['slug' => 'predikant'], ['naam' => 'Predikant']));

        Bericht::query()->create([
            'titel' => 'Voor iedereen',
            'inhoud' => 'Algemeen bericht',
            'auteur_id' => $admin->id,
            'doelgroep' => Bericht::DOELGROEP_ALLE,
            'kanaal' => Bericht::KANAAL_INTERN,
            'gepubliceerd_op' => now()->subHour(),
        ]);

        Bericht::query()->create([
            'titel' => 'Concept',
            'inhoud' => 'Nog niet live',
            'auteur_id' => $admin->id,
            'doelgroep' => Bericht::DOELGROEP_ALLE,
            'kanaal' => Bericht::KANAAL_INTERN,
            'gepubliceerd_op' => null,
        ]);

        $this->actingAs($predikant);
        session(['two_factor_verified' => true]);

        $response = $this->getJson('/api/berichten/inbox');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $response->assertJsonPath('data.0.titel', 'Voor iedereen');
        $response->assertJsonPath('data.0.gelezen', false);
    }

    public function test_gebruiker_kan_bericht_markeer_gelezen(): void
    {
        $admin = User::factory()->create();
        $predikant = User::factory()->create();
        $predikant->roles()->attach(Role::query()->firstOrCreate(['slug' => 'predikant'], ['naam' => 'Predikant']));

        $bericht = Bericht::query()->create([
            'titel' => 'Lees mij',
            'inhoud' => 'Tekst',
            'auteur_id' => $admin->id,
            'doelgroep' => Bericht::DOELGROEP_ALLE,
            'kanaal' => Bericht::KANAAL_INTERN,
            'gepubliceerd_op' => now()->subHour(),
        ]);

        $this->actingAs($predikant);
        session(['two_factor_verified' => true]);

        $this->postJson('/api/berichten/'.$bericht->id.'/gelezen')->assertOk();

        $count = $this->getJson('/api/berichten/unread-count');
        $count->assertJsonPath('data.count', 0);
    }

    public function test_gebruiker_kan_bericht_markeer_ongelezen(): void
    {
        $admin = User::factory()->create();
        $predikant = User::factory()->create();
        $predikant->roles()->attach(Role::query()->firstOrCreate(['slug' => 'predikant'], ['naam' => 'Predikant']));

        $bericht = Bericht::query()->create([
            'titel' => 'Lees mij',
            'inhoud' => 'Tekst',
            'auteur_id' => $admin->id,
            'doelgroep' => Bericht::DOELGROEP_ALLE,
            'kanaal' => Bericht::KANAAL_INTERN,
            'gepubliceerd_op' => now()->subHour(),
        ]);

        $this->actingAs($predikant);
        session(['two_factor_verified' => true]);

        $this->postJson('/api/berichten/'.$bericht->id.'/gelezen')->assertOk();
        $this->deleteJson('/api/berichten/'.$bericht->id.'/gelezen')->assertOk();

        $this->getJson('/api/berichten/unread-count')
            ->assertJsonPath('data.count', 1);

        $this->getJson('/api/berichten/inbox')
            ->assertJsonPath('data.0.gelezen', false);
    }

    public function test_gebruiker_kan_bericht_naar_prullenbak_verplaatsen(): void
    {
        $admin = User::factory()->create();
        $predikant = User::factory()->create();
        $predikant->roles()->attach(Role::query()->firstOrCreate(['slug' => 'predikant'], ['naam' => 'Predikant']));

        $bericht = Bericht::query()->create([
            'titel' => 'Verwijder mij',
            'inhoud' => 'Tekst',
            'auteur_id' => $admin->id,
            'doelgroep' => Bericht::DOELGROEP_ALLE,
            'kanaal' => Bericht::KANAAL_INTERN,
            'gepubliceerd_op' => now()->subHour(),
        ]);

        $this->actingAs($predikant);
        session(['two_factor_verified' => true]);

        $this->deleteJson('/api/berichten/'.$bericht->id)->assertOk();

        $this->getJson('/api/berichten/inbox')->assertJsonCount(0, 'data');
        $this->getJson('/api/berichten/prullenbak')
            ->assertJsonPath('data.0.titel', 'Verwijder mij')
            ->assertJsonPath('data.0.verwijderd', true);
    }

    public function test_gebruiker_kan_bericht_uit_prullenbak_herstellen(): void
    {
        $admin = User::factory()->create();
        $predikant = User::factory()->create();
        $predikant->roles()->attach(Role::query()->firstOrCreate(['slug' => 'predikant'], ['naam' => 'Predikant']));

        $bericht = Bericht::query()->create([
            'titel' => 'Herstel mij',
            'inhoud' => 'Tekst',
            'auteur_id' => $admin->id,
            'doelgroep' => Bericht::DOELGROEP_ALLE,
            'kanaal' => Bericht::KANAAL_INTERN,
            'gepubliceerd_op' => now()->subHour(),
        ]);

        $this->actingAs($predikant);
        session(['two_factor_verified' => true]);

        $this->deleteJson('/api/berichten/'.$bericht->id)->assertOk();
        $this->postJson('/api/berichten/'.$bericht->id.'/herstel')->assertOk();

        $this->getJson('/api/berichten/inbox')
            ->assertJsonPath('data.0.titel', 'Herstel mij');
        $this->getJson('/api/berichten/prullenbak')->assertJsonCount(0, 'data');
    }

    public function test_prullenbak_wordt_opgeschoond_na_dertig_dagen(): void
    {
        $admin = User::factory()->create();
        $predikant = User::factory()->create();

        $bericht = Bericht::query()->create([
            'titel' => 'Oud bericht',
            'inhoud' => 'Tekst',
            'auteur_id' => $admin->id,
            'doelgroep' => Bericht::DOELGROEP_ALLE,
            'kanaal' => Bericht::KANAAL_INTERN,
            'gepubliceerd_op' => now()->subDays(60),
        ]);

        DB::table('bericht_verwijderd')->insert([
            'bericht_id' => $bericht->id,
            'user_id' => $predikant->id,
            'verwijderd_op' => now()->subDays(31),
        ]);

        $verwijderd = app(BerichtService::class)->opschonenPrullenbak(30);

        $this->assertSame(1, $verwijderd);
        $this->assertDatabaseMissing('bericht_verwijderd', [
            'bericht_id' => $bericht->id,
            'user_id' => $predikant->id,
        ]);
        $this->assertDatabaseHas('berichten', ['id' => $bericht->id]);
    }
}

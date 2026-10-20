<?php

declare(strict_types=1);

namespace Tests\Feature\Beheer;

use App\Models\Bijzonderheid;
use App\Models\Dienst;
use App\Models\Gemeente;
use App\Models\Role;
use App\Models\User;
use App\Support\BijzonderheidCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BijzonderheidTest extends TestCase
{
    use RefreshDatabase;

    public function test_bijzonderheden_worden_automatisch_gevuld_bij_lege_database(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->firstOrCreate(['slug' => 'beheerder'], ['naam' => 'Beheerder']));

        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $this->getJson('/api/beheer/bijzonderheden')
            ->assertOk()
            ->assertJsonCount(count(BijzonderheidCatalog::NAMEN), 'data');
    }

    public function test_contactpersoon_met_rooster_scope_kan_bijzonderheden_ophalen(): void
    {
        $gemeente = Gemeente::factory()->create();
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->firstOrCreate(['slug' => 'gebruiker'], ['naam' => 'Gebruiker']));
        $user->functies()->attach(
            \App\Models\Functie::query()->firstOrCreate(['slug' => 'contactpersoon'], ['naam' => 'Contactpersoon'])
        );
        $user->gemeentes()->attach($gemeente->id);

        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $this->getJson('/api/beheer/bijzonderheden')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'naam']]]);
    }

    public function test_verwijderde_bijzonderheden_komen_niet_in_lijst(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->firstOrCreate(['slug' => 'beheerder'], ['naam' => 'Beheerder']));

        Bijzonderheid::query()->firstOrCreate(['naam' => 'Dankdienst']);
        Bijzonderheid::query()->firstOrCreate(['naam' => 'Kerstdienst']);

        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $namen = collect($this->getJson('/api/beheer/bijzonderheden')->json('data'))->pluck('naam');

        $this->assertFalse($namen->contains('Dankdienst'));
        $this->assertFalse($namen->contains('Kerstdienst'));
    }

    public function test_uitgesloten_bijzonderheid_niet_selecteerbaar_ook_bij_gebruik_in_dienst(): void
    {
        $gemeente = Gemeente::factory()->create();
        $dank = Bijzonderheid::query()->create(['naam' => 'Dankdienst']);
        Dienst::factory()->create([
            'gemeente_id' => $gemeente->id,
            'type' => 'bijzonder',
            'bijzonderheid_id' => $dank->id,
        ]);

        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->firstOrCreate(['slug' => 'beheerder'], ['naam' => 'Beheerder']));

        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $namen = collect($this->getJson('/api/beheer/bijzonderheden')->json('data'))->pluck('naam');

        $this->assertFalse($namen->contains('Dankdienst'));
        $this->assertTrue($namen->contains('Opdragingsdienst'));
    }
}

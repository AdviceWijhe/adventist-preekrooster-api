<?php

declare(strict_types=1);

namespace Tests\Feature\Predikant;

use App\Models\Functie;
use App\Models\Role;
use App\Models\User;
use App\Services\Instellingen\InstellingenService;
use App\Models\Option;
use Database\Seeders\FunctieSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactpersoonTest extends TestCase
{
    use RefreshDatabase;

    public function test_predikant_kan_contactpersonen_met_avg_consent_ophalen(): void
    {
        Option::setValue(InstellingenService::KEY_AVG_ENABLED, '1');
        $this->seed(FunctieSeeder::class);

        $predikant = User::factory()->create(['active' => true]);
        $predikant->roles()->attach(Role::query()->firstOrCreate(['slug' => 'gebruiker'], ['naam' => 'Gebruiker']));
        $predikant->functies()->attach(Functie::query()->where('slug', 'predikant')->firstOrFail()->id);

        $contact = User::factory()->create([
            'email' => 'cp@example.test',
            'telefoonnummer' => '0612345678',
            'avg_consent_at' => now(),
        ]);
        $contact->functies()->attach(Functie::query()->where('slug', 'contactpersoon')->firstOrFail()->id);

        $this->actingAs($predikant);
        session(['two_factor_verified' => true]);

        $this->getJson('/api/predikant/contactpersonen')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', 'cp@example.test');
    }

    public function test_predikant_met_avg_consent_staat_in_overzicht(): void
    {
        Option::setValue(InstellingenService::KEY_AVG_ENABLED, '1');
        $this->seed(FunctieSeeder::class);

        $viewer = User::factory()->create(['active' => true]);
        $viewer->roles()->attach(Role::query()->firstOrCreate(['slug' => 'gebruiker'], ['naam' => 'Gebruiker']));
        $viewer->functies()->attach(Functie::query()->where('slug', 'spreker')->firstOrFail()->id);

        $predikant = User::factory()->create([
            'voornaam' => 'Test',
            'achternaam' => 'Predikant',
            'email' => 'pred@example.test',
            'telefoonnummer' => '0612345678',
            'avg_consent_at' => now(),
        ]);
        $predikant->functies()->attach(Functie::query()->where('slug', 'predikant')->firstOrFail()->id);

        $this->actingAs($viewer);
        session(['two_factor_verified' => true]);

        $this->getJson('/api/predikant/contactpersonen')
            ->assertOk()
            ->assertJsonPath('data.0.email', 'pred@example.test');
    }

    public function test_inactieve_gebruiker_met_avg_niet_in_overzicht(): void
    {
        Option::setValue(InstellingenService::KEY_AVG_ENABLED, '1');
        $this->seed(FunctieSeeder::class);

        $viewer = User::factory()->create(['active' => true]);
        $viewer->roles()->attach(Role::query()->firstOrCreate(['slug' => 'gebruiker'], ['naam' => 'Gebruiker']));
        $viewer->functies()->attach(Functie::query()->where('slug', 'spreker')->firstOrFail()->id);

        $inactief = User::factory()->create([
            'email' => 'inactief@example.test',
            'telefoonnummer' => '0612345678',
            'avg_consent_at' => now(),
            'active' => false,
        ]);
        $inactief->functies()->attach(Functie::query()->where('slug', 'contactpersoon')->firstOrFail()->id);

        $this->actingAs($viewer);
        session(['two_factor_verified' => true]);

        $this->getJson('/api/predikant/contactpersonen')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}

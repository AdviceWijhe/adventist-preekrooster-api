<?php

declare(strict_types=1);

namespace Tests\Feature\Predikant;

use App\Models\Role;
use App\Models\User;
use App\Models\UserBeschikbaarheid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BeschikbaarheidTest extends TestCase
{
    use RefreshDatabase;

    private function predikant(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->firstOrCreate(['slug' => 'predikant'], ['naam' => 'Predikant']));

        return $user;
    }

    public function test_predikant_kan_onbeschikbare_periode_toevoegen(): void
    {
        $user = $this->predikant();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $van = Carbon::now()->addDays(7)->format('Y-m-d');
        $tot = Carbon::now()->addDays(14)->format('Y-m-d');

        $response = $this->postJson('/api/predikant/beschikbaarheid', [
            'datum_van' => $van,
            'datum_tot' => $tot,
            'opmerking' => 'Zomervakantie',
        ]);

        $response->assertStatus(201);
        $this->assertTrue(
            UserBeschikbaarheid::query()
                ->where('user_id', $user->id)
                ->whereDate('datum_van', $van)
                ->exists()
        );
    }

    public function test_predikant_kan_eigen_beschikbaarheid_inzien(): void
    {
        $user = $this->predikant();
        UserBeschikbaarheid::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $response = $this->getJson('/api/predikant/beschikbaarheid');

        $response->assertStatus(200)->assertJsonCount(1, 'data');
    }

    public function test_contactpersoon_mag_geen_beschikbaarheid_beheren(): void
    {
        $this->seed(\Database\Seeders\FunctieSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->firstOrCreate(['slug' => 'gebruiker'], ['naam' => 'Gebruiker']));
        $user->functies()->attach(\App\Models\Functie::query()->where('slug', 'contactpersoon')->value('id'));

        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $this->getJson('/api/predikant/beschikbaarheid')->assertStatus(403);
        $this->postJson('/api/predikant/beschikbaarheid', [
            'datum_van' => now()->addDays(7)->format('Y-m-d'),
            'datum_tot' => now()->addDays(14)->format('Y-m-d'),
        ])->assertStatus(403);
    }

    public function test_datum_tot_moet_op_of_na_datum_van_liggen(): void
    {
        $user = $this->predikant();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $response = $this->postJson('/api/predikant/beschikbaarheid', [
            'datum_van' => '2026-07-14',
            'datum_tot' => '2026-07-01',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['datum_tot']);
    }

    public function test_predikant_kan_enkele_dag_als_onbeschikbaar_markeren(): void
    {
        $user = $this->predikant();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $dag = Carbon::now()->addDays(3)->format('Y-m-d');

        $response = $this->postJson('/api/predikant/beschikbaarheid', [
            'datum_van' => $dag,
            'datum_tot' => $dag,
            'opmerking' => 'Dag vrij',
        ]);

        $response->assertStatus(201);
        $this->assertTrue(
            UserBeschikbaarheid::query()
                ->where('user_id', $user->id)
                ->whereDate('datum_van', $dag)
                ->whereDate('datum_tot', $dag)
                ->where('opmerking', 'Dag vrij')
                ->exists()
        );
    }

    public function test_predikant_kan_eigen_beschikbaarheid_aanpassen(): void
    {
        $user = $this->predikant();
        $beschikbaarheid = UserBeschikbaarheid::factory()->create([
            'user_id' => $user->id,
            'datum_van' => Carbon::now()->addDays(5),
            'datum_tot' => Carbon::now()->addDays(5),
            'opmerking' => 'Vrij',
        ]);

        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $nieuweDag = Carbon::now()->addDays(10)->format('Y-m-d');

        $response = $this->putJson("/api/predikant/beschikbaarheid/{$beschikbaarheid->id}", [
            'datum_van' => $nieuweDag,
            'datum_tot' => $nieuweDag,
            'opmerking' => 'Aangepast',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.opmerking', 'Aangepast');

        $beschikbaarheid->refresh();
        $this->assertSame($nieuweDag, $beschikbaarheid->datum_van->format('Y-m-d'));
        $this->assertSame($nieuweDag, $beschikbaarheid->datum_tot->format('Y-m-d'));
    }

    public function test_predikant_kan_beschikbaarheid_van_ander_niet_aanpassen(): void
    {
        $eigenaar = $this->predikant();
        $ander = $this->predikant();
        $beschikbaarheid = UserBeschikbaarheid::factory()->create(['user_id' => $eigenaar->id]);

        $this->actingAs($ander);
        session(['two_factor_verified' => true]);

        $response = $this->putJson("/api/predikant/beschikbaarheid/{$beschikbaarheid->id}", [
            'datum_van' => Carbon::now()->addDays(7)->format('Y-m-d'),
            'datum_tot' => Carbon::now()->addDays(7)->format('Y-m-d'),
        ]);

        $response->assertForbidden();
    }
}

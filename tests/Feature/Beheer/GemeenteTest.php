<?php

declare(strict_types=1);

namespace Tests\Feature\Beheer;

use App\Models\Dienst;
use App\Models\Gemeente;
use App\Models\Publicatie;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GemeenteTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $role = Role::query()->firstOrCreate(
            ['slug' => 'admin'],
            ['naam' => 'Administrator']
        );
        $user->roles()->attach($role);

        return $user;
    }

    public function test_admin_kan_gemeentes_opvragen(): void
    {
        Gemeente::factory()->count(2)->create();
        $user = $this->adminUser();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $response = $this->getJson('/api/beheer/gemeentes');

        $response->assertStatus(200)->assertJsonCount(2, 'data');
    }

    public function test_admin_kan_gemeentes_zoeken_op_naam_en_plaats(): void
    {
        Gemeente::factory()->create([
            'naam' => 'Amsterdam Zuid',
            'plaats' => 'Amsterdam',
        ]);
        Gemeente::factory()->create([
            'naam' => 'Utrecht Centrum',
            'plaats' => 'Utrecht',
        ]);

        $user = $this->adminUser();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $response = $this->getJson('/api/beheer/gemeentes?search=amsterdam');
        $response->assertStatus(200)->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.naam', 'Amsterdam Zuid');

        $responseGeenMatch = $this->getJson('/api/beheer/gemeentes?search=onvindbaar');
        $responseGeenMatch->assertStatus(200)->assertJsonCount(0, 'data');
    }

    public function test_gemeentes_worden_alfabetisch_op_naam_gesorteerd(): void
    {
        Gemeente::factory()->create(['naam' => 'Zwolle', 'volgorde' => 1]);
        Gemeente::factory()->create(['naam' => 'Amsterdam', 'volgorde' => 2]);
        Gemeente::factory()->create(['naam' => 'Haarlem', 'volgorde' => 3]);

        $user = $this->adminUser();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $response = $this->getJson('/api/beheer/gemeentes');

        $response->assertStatus(200);
        $response->assertJsonPath('data.0.naam', 'Amsterdam');
        $response->assertJsonPath('data.1.naam', 'Haarlem');
        $response->assertJsonPath('data.2.naam', 'Zwolle');
    }

    public function test_admin_kan_gemeente_aanmaken(): void
    {
        $user = $this->adminUser();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $response = $this->postJson('/api/beheer/gemeentes', [
            'naam' => 'Amsterdam Zuid',
            'naam_kort' => "A'dam Zuid",
            'taal' => 'nl',
        ]);

        $response->assertStatus(201)->assertJsonPath('data.naam', 'Amsterdam Zuid');
    }

    public function test_gemeente_naam_is_verplicht(): void
    {
        $user = $this->adminUser();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $response = $this->postJson('/api/beheer/gemeentes', ['naam_kort' => 'Test']);

        $response->assertStatus(422)->assertJsonValidationErrors(['naam']);
    }

    public function test_admin_kan_gemeente_verwijderen_zonder_koppelingen(): void
    {
        $gemeente = Gemeente::factory()->create();
        $user = $this->adminUser();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $response = $this->deleteJson('/api/beheer/gemeentes/'.$gemeente->id);

        $response->assertStatus(204);
        $this->assertDatabaseMissing('gemeentes', ['id' => $gemeente->id]);
    }

    public function test_gemeente_verwijderen_geblokkeerd_door_dienst(): void
    {
        $gemeente = Gemeente::factory()->create();
        Dienst::factory()->create(['gemeente_id' => $gemeente->id]);
        $user = $this->adminUser();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $response = $this->deleteJson('/api/beheer/gemeentes/'.$gemeente->id);

        $response->assertStatus(409)->assertJsonPath(
            'message',
            'Deze gemeente kan niet worden verwijderd: er zijn nog diensten in het rooster. Zet de gemeente inactief of verwijder eerst de diensten.',
        );
        $this->assertDatabaseHas('gemeentes', ['id' => $gemeente->id]);
    }

    public function test_gemeente_verwijderen_geblokkeerd_door_publicatie(): void
    {
        $gemeente = Gemeente::factory()->create();
        Publicatie::query()->create([
            'gemeente_id' => $gemeente->id,
            'periode' => '2026-04',
            'gepubliceerd' => false,
        ]);
        $user = $this->adminUser();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $response = $this->deleteJson('/api/beheer/gemeentes/'.$gemeente->id);

        $response->assertStatus(409);
        $response->assertJsonPath(
            'message',
            'Deze gemeente kan niet worden verwijderd: er zijn publicaties gekoppeld. Zet de gemeente inactief of wijzig eerst de publicaties.',
        );
        $this->assertDatabaseHas('gemeentes', ['id' => $gemeente->id]);
    }
}

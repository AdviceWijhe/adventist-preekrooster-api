<?php

declare(strict_types=1);

namespace Tests\Feature\Beheer;

use App\Models\District;
use App\Models\Gemeente;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DistrictTest extends TestCase
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

    public function test_admin_kan_districts_opvragen(): void
    {
        District::factory()->count(3)->create();
        $user = $this->adminUser();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $response = $this->getJson('/api/beheer/districts');

        $response->assertStatus(200)->assertJsonCount(3, 'data');
    }

    public function test_admin_kan_districts_zoeken_op_naam(): void
    {
        District::factory()->create(['naam' => 'Noord-West']);
        District::factory()->create(['naam' => 'Zuid-Oost']);

        $user = $this->adminUser();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $response = $this->getJson('/api/beheer/districts?search=noord');
        $response->assertStatus(200)->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.naam', 'Noord-West');

        $responseGeenMatch = $this->getJson('/api/beheer/districts?search=onvindbaar');
        $responseGeenMatch->assertStatus(200)->assertJsonCount(0, 'data');
    }

    public function test_admin_kan_district_aanmaken(): void
    {
        $user = $this->adminUser();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $response = $this->postJson('/api/beheer/districts', ['naam' => 'Noord-Holland']);

        $response->assertStatus(201)->assertJsonPath('data.naam', 'Noord-Holland');
        $this->assertDatabaseHas('districts', ['naam' => 'Noord-Holland']);
    }

    public function test_niet_admin_heeft_geen_toegang(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $response = $this->getJson('/api/beheer/districts');

        $response->assertStatus(403);
    }

    public function test_admin_kan_district_aanmaken_met_visible_false(): void
    {
        $user = $this->adminUser();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $response = $this->postJson('/api/beheer/districts', [
            'naam' => 'Zuid',
            'visible' => false,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('districts', [
            'naam' => 'Zuid',
            'visible' => 0,
        ]);
    }

    public function test_admin_kan_district_wijzigen_visible(): void
    {
        $district = District::factory()->create(['visible' => false]);
        $user = $this->adminUser();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $response = $this->putJson('/api/beheer/districts/'.$district->id, [
            'naam' => $district->naam,
            'visible' => true,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('districts', [
            'id' => $district->id,
            'visible' => 1,
        ]);
    }

    public function test_admin_kan_district_verwijderen_zonder_gemeenten(): void
    {
        $district = District::factory()->create();
        $user = $this->adminUser();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $response = $this->deleteJson('/api/beheer/districts/'.$district->id);

        $response->assertStatus(204);
        $this->assertDatabaseMissing('districts', ['id' => $district->id]);
    }

    public function test_district_verwijderen_geblokkeerd_door_gemeente(): void
    {
        $district = District::factory()->create();
        Gemeente::factory()->create(['district_id' => $district->id]);
        $user = $this->adminUser();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $response = $this->deleteJson('/api/beheer/districts/'.$district->id);

        $response->assertStatus(409);
        $response->assertJsonPath(
            'message',
            'Dit district kan niet worden verwijderd: er hangen nog gemeenten aan. Verplaats of verwijder eerst de gemeenten, of zet het district onzichtbaar.',
        );
        $this->assertDatabaseHas('districts', ['id' => $district->id]);
    }
}

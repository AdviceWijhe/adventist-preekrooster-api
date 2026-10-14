<?php

declare(strict_types=1);

namespace Tests\Feature\Beheer;

use App\Models\PublicNavigationItem;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicNavigationTest extends TestCase
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

    public function test_admin_kan_publieke_navigatie_opvragen(): void
    {
        PublicNavigationItem::query()->create([
            'key' => 'home',
            'label_nl' => 'Start',
            'label_en' => 'Home',
            'url' => '/',
            'display_order' => 10,
            'visible' => true,
            'external' => false,
        ]);
        $user = $this->adminUser();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $response = $this->getJson('/api/beheer/publieke-navigatie');

        $response
            ->assertStatus(200)
            ->assertJsonPath('data.0.id', 'home')
            ->assertJsonPath('data.0.order', 10);
    }

    public function test_admin_kan_navigatie_item_aanmaken(): void
    {
        $user = $this->adminUser();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $response = $this->postJson('/api/beheer/publieke-navigatie', [
            'key' => 'livestream',
            'label_nl' => 'Livestream',
            'label_en' => 'Livestream',
            'url' => 'https://example.org/live',
            'display_order' => 20,
            'visible' => true,
            'external' => true,
        ]);

        $response->assertStatus(201)->assertJsonPath('data.id', 'livestream');
        $this->assertDatabaseHas('public_navigation_items', ['key' => 'livestream']);
    }

    public function test_admin_kan_navigatie_item_bijwerken(): void
    {
        $item = PublicNavigationItem::query()->create([
            'key' => 'home',
            'label_nl' => 'Start',
            'label_en' => 'Home',
            'url' => '/',
            'display_order' => 10,
            'visible' => true,
            'external' => false,
        ]);
        $user = $this->adminUser();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $response = $this->putJson('/api/beheer/publieke-navigatie/'.$item->key, [
            'key' => 'home',
            'label_nl' => 'Homepage',
            'label_en' => 'Homepage',
            'url' => '/',
            'display_order' => 5,
            'visible' => false,
            'external' => false,
        ]);

        $response->assertStatus(200)->assertJsonPath('data.label_nl', 'Homepage');
        $this->assertDatabaseHas('public_navigation_items', [
            'key' => 'home',
            'label_nl' => 'Homepage',
            'display_order' => 5,
            'visible' => false,
        ]);
    }

    public function test_admin_kan_navigatie_item_verwijderen(): void
    {
        $item = PublicNavigationItem::query()->create([
            'key' => 'home',
            'label_nl' => 'Start',
            'label_en' => 'Home',
            'url' => '/',
            'display_order' => 10,
            'visible' => true,
            'external' => false,
        ]);
        $user = $this->adminUser();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $response = $this->deleteJson('/api/beheer/publieke-navigatie/'.$item->key);

        $response->assertStatus(204);
        $this->assertDatabaseMissing('public_navigation_items', ['key' => 'home']);
    }

    public function test_externe_navigatie_url_mag_geen_javascript_scheme(): void
    {
        $user = $this->adminUser();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $response = $this->postJson('/api/beheer/publieke-navigatie', [
            'key' => 'evil',
            'label_nl' => 'Evil',
            'label_en' => 'Evil',
            'url' => 'javascript:alert(1)',
            'display_order' => 20,
            'visible' => true,
            'external' => true,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['url']);
    }
}

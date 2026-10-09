<?php

declare(strict_types=1);

namespace Tests\Feature\Beheer;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->firstOrCreate(['slug' => 'admin'], ['naam' => 'Administrator']));

        return $user;
    }

    public function test_admin_kan_branding_instellingen_ophalen_en_opslaan(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        session(['two_factor_verified' => true]);

        $this->getJson('/api/beheer/branding')
            ->assertOk()
            ->assertJsonStructure(['data' => ['branding_app_name', 'branding_primary_color']]);

        $this->putJson('/api/beheer/branding', [
            'branding_app_name' => 'Test Naam',
            'branding_primary_color' => '#123456',
            'branding_secondary_color' => '#223344',
            'branding_accent_color' => '#DDEEFF',
            'branding_from_name' => 'Test Afzender',
            'branding_footer_text' => 'Footer',
        ])->assertOk()
            ->assertJsonPath('data.branding_app_name', 'Test Naam');
    }

    public function test_beheerder_kan_branding_niet_ophalen_of_opslaan(): void
    {
        $beheerder = User::factory()->create(['statistieken_toegang' => true]);
        $beheerder->roles()->attach(Role::query()->firstOrCreate(['slug' => 'beheerder'], ['naam' => 'Beheerder']));
        $this->actingAs($beheerder);
        session(['two_factor_verified' => true]);

        $this->getJson('/api/beheer/branding')->assertStatus(403);
        $this->putJson('/api/beheer/branding', [
            'branding_app_name' => 'Test Naam',
            'branding_primary_color' => '#123456',
        ])->assertStatus(403);
    }
}

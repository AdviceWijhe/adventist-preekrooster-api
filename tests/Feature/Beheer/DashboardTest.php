<?php

declare(strict_types=1);

namespace Tests\Feature\Beheer;

use App\Models\Dienst;
use App\Models\Gemeente;
use App\Models\Role;
use App\Models\Spreekbeurt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_beheerder_kan_dashboard_stats_opvragen(): void
    {
        $beheerder = User::factory()->create();
        $beheerder->roles()->attach(Role::query()->firstOrCreate(['slug' => 'beheerder'], ['naam' => 'Beheerder']));
        $this->actingAs($beheerder);
        session(['two_factor_verified' => true]);

        $gemeente = Gemeente::factory()->create();
        $metSpreker = Dienst::factory()->create([
            'gemeente_id' => $gemeente->id,
            'datum' => now()->addWeek(),
        ]);
        Spreekbeurt::factory()->create([
            'dienst_id' => $metSpreker->id,
            'bevestigd' => 1,
        ]);

        Dienst::factory()->create([
            'gemeente_id' => $gemeente->id,
            'datum' => now()->addWeeks(2),
        ]);

        $response = $this->getJson('/api/beheer/dashboard/stats');

        $response->assertOk()->assertJson(['onbevestigd' => 1]);

        $response->assertJsonStructure([
            'predikanten',
            'gemeentes',
            'gebruikers',
            'onbevestigd',
            'sparklines' => [
                'predikanten',
                'gemeentes',
                'gebruikers',
                'onbevestigd',
            ],
            'sparkline_period' => ['months', 'from', 'to'],
            'sparkline_changes' => [
                'predikanten',
                'gemeentes',
                'gebruikers',
                'onbevestigd',
            ],
        ]);

        $this->assertCount(6, $response->json('sparklines.onbevestigd'));
    }
}

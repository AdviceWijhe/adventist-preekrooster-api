<?php

declare(strict_types=1);

namespace Tests\Feature\Rooster;

use App\Models\Dienst;
use App\Models\Gemeente;
use App\Models\GemeenteRoosterSluiting;
use App\Models\Role;
use App\Models\User;
use App\Services\RoosterStandaardSlotsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoosterStandaardSlotsTest extends TestCase
{
    use RefreshDatabase;

    private function beheerder(): User
    {
        $user = User::factory()->create(['active' => true]);
        $user->roles()->attach(Role::query()->firstOrCreate(['slug' => 'beheerder'], ['naam' => 'Beheerder']));

        return $user;
    }

    public function test_ensure_maakt_standaard_eredienst_per_zaterdag(): void
    {
        $gemeente = Gemeente::factory()->create(['active' => true, 'taal' => 'nl']);

        app(RoosterStandaardSlotsService::class)->ensureVoorMaand('2026-05');

        $this->assertTrue(
            Dienst::query()
                ->where('gemeente_id', $gemeente->id)
                ->where('type', 'reguliere_dienst')
                ->whereDate('datum', '2026-05-02')
                ->exists()
        );
    }

    public function test_sluiting_blokkeert_standaard_slot(): void
    {
        $gemeente = Gemeente::factory()->create(['active' => true]);
        GemeenteRoosterSluiting::query()->create([
            'gemeente_id' => $gemeente->id,
            'datum' => '2026-05-02',
        ]);

        app(RoosterStandaardSlotsService::class)->ensureVoorMaand('2026-05');

        $this->assertFalse(
            Dienst::query()
                ->where('gemeente_id', $gemeente->id)
                ->whereDate('datum', '2026-05-02')
                ->exists()
        );
    }

    public function test_sluit_dag_api(): void
    {
        $gemeente = Gemeente::factory()->create(['active' => true]);
        Dienst::factory()->create([
            'gemeente_id' => $gemeente->id,
            'datum' => '2026-05-09',
            'type' => 'reguliere_dienst',
        ]);

        $this->actingAs($this->beheerder());
        session(['two_factor_verified' => true]);

        $this->postJson('/api/beheer/roosters/dag-sluiten', [
            'gemeente_id' => $gemeente->id,
            'datum' => '2026-05-09',
        ])->assertOk();

        $this->assertTrue(
            GemeenteRoosterSluiting::query()
                ->where('gemeente_id', $gemeente->id)
                ->whereDate('datum', '2026-05-09')
                ->exists()
        );
        $this->assertFalse(
            Dienst::query()
                ->where('gemeente_id', $gemeente->id)
                ->whereDate('datum', '2026-05-09')
                ->exists()
        );
    }

    public function test_open_dag_maakt_standaard_dienst(): void
    {
        $gemeente = Gemeente::factory()->create(['active' => true]);
        GemeenteRoosterSluiting::query()->create([
            'gemeente_id' => $gemeente->id,
            'datum' => '2026-05-16',
        ]);

        $this->actingAs($this->beheerder());
        session(['two_factor_verified' => true]);

        $this->postJson('/api/beheer/roosters/dag-openen', [
            'gemeente_id' => $gemeente->id,
            'datum' => '2026-05-16',
        ])->assertOk()
            ->assertJsonPath('data.type', 'reguliere_dienst');

        $this->assertDatabaseMissing('gemeente_rooster_sluitingen', [
            'gemeente_id' => $gemeente->id,
            'datum' => '2026-05-16',
        ]);
    }
}

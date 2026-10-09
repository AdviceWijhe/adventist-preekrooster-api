<?php

declare(strict_types=1);

namespace Tests\Feature\Avg;

use App\Models\Option;
use App\Models\Role;
use App\Models\User;
use App\Services\Instellingen\InstellingenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AvgStatusTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create(['avg_consent_at' => now()]);
        $user->roles()->attach(Role::query()->firstOrCreate(['slug' => 'admin'], ['naam' => 'Administrator']));

        return $user;
    }

    private function predikant(): User
    {
        $user = User::factory()->create(['active' => true]);
        $user->roles()->attach(Role::query()->firstOrCreate(['slug' => 'predikant'], ['naam' => 'Predikant']));

        return $user;
    }

    protected function setUp(): void
    {
        parent::setUp();
        Option::setValue(InstellingenService::KEY_AVG_DAGEN, '14');
    }

    private function enableAvg(): void
    {
        Option::setValue(InstellingenService::KEY_AVG_ENABLED, '1');
    }

    public function test_status_na_api_weigering(): void
    {
        $this->enableAvg();
        $user = $this->predikant();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $this->postJson('/api/auth/avg-consent', ['akkoord' => false])->assertOk();

        $admin = $this->admin();
        $this->actingAs($admin);
        session(['two_factor_verified' => true]);

        $this->getJson('/api/beheer/avg/status')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $user->id)
            ->assertJsonPath('data.0.status', 'geweigerd')
            ->assertJsonPath('data.0.dagen_resterend', 14);
    }

    public function test_status_toont_open_gebruiker_zonder_consent(): void
    {
        $admin = $this->admin();
        $open = $this->predikant();
        $open->forceFill(['avg_deferred_at' => now()])->save();
        $this->actingAs($admin);
        session(['two_factor_verified' => true]);

        $this->getJson('/api/beheer/avg/status?filter=open')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $open->id)
            ->assertJsonPath('data.0.status', 'open')
            ->assertJsonPath('data.0.dagen_resterend', 14);
    }

    public function test_filter_open_sluit_gebruikers_zonder_uitstel_uit(): void
    {
        $admin = $this->admin();
        $this->predikant();
        $this->actingAs($admin);
        session(['two_factor_verified' => true]);

        $this->getJson('/api/beheer/avg/status?filter=open')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_status_toont_geweigerde_gebruiker_met_dagen_resterend(): void
    {
        Carbon::setTestNow('2026-10-06 12:00:00');
        $admin = $this->admin();
        $geweigerd = $this->predikant();
        $geweigerd->forceFill(['avg_refused_at' => now()->subDays(4)->startOfDay()])->save();
        $this->actingAs($admin);
        session(['two_factor_verified' => true]);

        $this->getJson('/api/beheer/avg/status?filter=geweigerd')
            ->assertOk()
            ->assertJsonPath('data.0.id', $geweigerd->id)
            ->assertJsonPath('data.0.status', 'geweigerd')
            ->assertJsonPath('data.0.dagen_resterend', 10);

        Carbon::setTestNow();
    }

    public function test_status_sluit_gebruikers_met_akkoord_uit_bij_pending(): void
    {
        $admin = $this->admin();
        $akkoord = $this->predikant();
        $akkoord->forceFill([
            'avg_consent_at' => now(),
            'telefoonnummer' => '0612345678',
        ])->save();
        $this->actingAs($admin);
        session(['two_factor_verified' => true]);

        $this->getJson('/api/beheer/avg/status')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/beheer/avg/status?filter=akkoord')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $akkoord->id)
            ->assertJsonPath('data.0.status', 'akkoord');
    }

    public function test_status_sluit_beheerders_uit(): void
    {
        $admin = $this->admin();
        $beheerder = User::factory()->create();
        $beheerder->roles()->attach(Role::query()->firstOrCreate(['slug' => 'beheerder'], ['naam' => 'Beheerder']));
        $this->actingAs($admin);
        session(['two_factor_verified' => true]);

        $this->getJson('/api/beheer/avg/status')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/beheer/avg/status?filter=all')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_beheerder_krijgt_403(): void
    {
        $beheerder = User::factory()->create();
        $beheerder->roles()->attach(Role::query()->firstOrCreate(['slug' => 'beheerder'], ['naam' => 'Beheerder']));
        $this->actingAs($beheerder);
        session(['two_factor_verified' => true]);

        $this->getJson('/api/beheer/avg/status')->assertStatus(403);
    }

    public function test_filter_open_sluit_geweigerden_uit(): void
    {
        $admin = $this->admin();
        $open = $this->predikant();
        $open->forceFill(['avg_deferred_at' => now()])->save();
        $geweigerd = $this->predikant();
        $geweigerd->forceFill(['avg_refused_at' => now()])->save();
        $this->actingAs($admin);
        session(['two_factor_verified' => true]);

        $this->getJson('/api/beheer/avg/status?filter=open')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $open->id);
    }

    public function test_reset_wist_avg_velden_en_pending_toont_gebruiker(): void
    {
        $admin = $this->admin();
        $user = $this->predikant();
        $user->forceFill(['avg_consent_at' => now(), 'avg_refused_at' => null])->save();
        $this->actingAs($admin);
        session(['two_factor_verified' => true]);

        $this->postJson("/api/beheer/avg/gebruikers/{$user->id}/reset")
            ->assertOk()
            ->assertJsonPath('message', __('api.beheer.avg_reset_success'));

        $user->refresh();
        $this->assertNull($user->avg_consent_at);
        $this->assertNull($user->avg_refused_at);
        $this->assertNull($user->avg_deferred_at);

        $this->getJson('/api/beheer/avg/status')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $user->id)
            ->assertJsonPath('data.0.status', 'wacht');
    }

    public function test_filter_wacht_toont_gebruiker_zonder_uitstel(): void
    {
        $admin = $this->admin();
        $wacht = $this->predikant();
        $this->actingAs($admin);
        session(['two_factor_verified' => true]);

        $this->getJson('/api/beheer/avg/status?filter=wacht')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $wacht->id)
            ->assertJsonPath('data.0.status', 'wacht')
            ->assertJsonPath('data.0.dagen_resterend', 14);
    }

    public function test_stale_consent_zonder_telefoon_niet_in_akkoord_filter(): void
    {
        $this->enableAvg();
        $admin = $this->admin();
        $stale = $this->predikant();
        $stale->forceFill([
            'avg_consent_at' => now(),
            'telefoonnummer' => null,
            'mobiel' => null,
        ])->save();
        $this->actingAs($admin);
        session(['two_factor_verified' => true]);

        $this->getJson('/api/beheer/avg/status?filter=akkoord')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/beheer/avg/status?filter=wacht')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $stale->id)
            ->assertJsonPath('data.0.status', 'wacht')
            ->assertJsonPath('data.0.avg_consent_at', null);
    }

    public function test_reset_beheerder_is_verboden(): void
    {
        $admin = $this->admin();
        $beheerder = User::factory()->create();
        $beheerder->roles()->attach(Role::query()->firstOrCreate(['slug' => 'beheerder'], ['naam' => 'Beheerder']));
        $this->actingAs($admin);
        session(['two_factor_verified' => true]);

        $this->postJson("/api/beheer/avg/gebruikers/{$beheerder->id}/reset")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['gebruiker']);
    }
}

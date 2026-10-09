<?php

declare(strict_types=1);

namespace Tests\Feature\Beheer;

use App\Models\ChangeLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChangeLogTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->firstOrCreate(['slug' => 'admin'], ['naam' => 'Admin']));
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        return $user;
    }

    public function test_changelog_toont_alleen_vermeldingen_binnen_bewaartermijn(): void
    {
        $this->admin();

        $recent = ChangeLog::create(['actie' => 'Recent', 'model' => 'User']);
        $recent->forceFill(['created_at' => now()->subMonths(2)])->save();

        $verlopen = ChangeLog::create(['actie' => 'Te oud', 'model' => 'User']);
        $verlopen->forceFill(['created_at' => now()->subMonths(7)])->save();

        $response = $this->getJson('/api/beheer/changelog');

        $response->assertOk();
        $actiesResponse = collect($response->json('data'))->pluck('actie');
        $this->assertTrue($actiesResponse->contains('Recent'));
        $this->assertFalse($actiesResponse->contains('Te oud'));
    }

    public function test_changelog_kan_gefilterd_worden_op_maand(): void
    {
        $this->admin();

        $juli = ChangeLog::create(['actie' => 'In juli', 'model' => 'User']);
        $juli->forceFill(['created_at' => '2026-07-15 10:00:00'])->save();

        $juni = ChangeLog::create(['actie' => 'In juni', 'model' => 'User']);
        $juni->forceFill(['created_at' => '2026-06-15 10:00:00'])->save();

        $this->travelTo('2026-07-27 12:00:00');

        $response = $this->getJson('/api/beheer/changelog?maand=2026-07');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.actie', 'In juli');
    }

    public function test_export_geeft_csv_terug_met_alle_vermeldingen(): void
    {
        $admin = $this->admin();

        $entry = ChangeLog::create(['user_id' => $admin->id, 'actie' => 'Spreker gekoppeld', 'model' => 'Dienst']);
        $entry->forceFill(['created_at' => now()->subDays(2)])->save();

        $response = $this->get('/api/beheer/changelog/export');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=utf-8');
        $content = $response->getContent();
        $this->assertStringContainsString('Datum/tijd;Gebruiker;Actie;Onderdeel', $content);
        $this->assertStringContainsString('Spreker gekoppeld', $content);
        $this->assertStringContainsString('Dienst', $content);
    }

    public function test_export_respecteert_maand_filter(): void
    {
        $this->admin();

        $juli = ChangeLog::create(['actie' => 'In juli', 'model' => 'User']);
        $juli->forceFill(['created_at' => '2026-07-15 10:00:00'])->save();

        $juni = ChangeLog::create(['actie' => 'In juni', 'model' => 'User']);
        $juni->forceFill(['created_at' => '2026-06-15 10:00:00'])->save();

        $this->travelTo('2026-07-27 12:00:00');

        $response = $this->get('/api/beheer/changelog/export?maand=2026-07');

        $response->assertOk();
        $content = $response->getContent();
        $this->assertStringContainsString('In juli', $content);
        $this->assertStringNotContainsString('In juni', $content);
        $response->assertHeader('Content-Disposition', 'attachment; filename="changelog-2026-07.csv"');
    }

    public function test_export_vereist_authenticatie(): void
    {
        $this->getJson('/api/beheer/changelog/export')->assertStatus(401);
    }

    public function test_beheerder_kan_changelog_niet_exporteren(): void
    {
        $beheerder = User::factory()->create(['statistieken_toegang' => true]);
        $beheerder->roles()->attach(Role::query()->firstOrCreate(['slug' => 'beheerder'], ['naam' => 'Beheerder']));
        $this->actingAs($beheerder);
        session(['two_factor_verified' => true]);

        $this->get('/api/beheer/changelog/export')->assertStatus(403);
    }

    public function test_beheerder_kan_changelog_niet_legen(): void
    {
        ChangeLog::create(['actie' => 'Blijft staan', 'model' => 'User']);

        $beheerder = User::factory()->create(['statistieken_toegang' => true]);
        $beheerder->roles()->attach(Role::query()->firstOrCreate(['slug' => 'beheerder'], ['naam' => 'Beheerder']));
        $this->actingAs($beheerder);
        session(['two_factor_verified' => true]);

        $this->deleteJson('/api/beheer/changelog')->assertStatus(403);
        $this->assertDatabaseCount('change_log', 1);
    }

    public function test_admin_kan_changelog_legen(): void
    {
        $this->admin();

        ChangeLog::create(['actie' => 'Eerste', 'model' => 'User']);
        ChangeLog::create(['actie' => 'Tweede', 'model' => 'User']);

        $response = $this->deleteJson('/api/beheer/changelog');

        $response->assertOk()->assertJsonPath('data.verwijderd', 2);
        $this->assertDatabaseCount('change_log', 0);
    }

    public function test_legen_vereist_authenticatie(): void
    {
        $this->deleteJson('/api/beheer/changelog')->assertStatus(401);
    }

    public function test_maanden_geeft_alleen_maanden_met_vermeldingen_terug(): void
    {
        $this->admin();

        $juli = ChangeLog::create(['actie' => 'In juli', 'model' => 'User']);
        $juli->forceFill(['created_at' => '2026-07-15 10:00:00'])->save();

        $mei = ChangeLog::create(['actie' => 'In mei', 'model' => 'User']);
        $mei->forceFill(['created_at' => '2026-05-15 10:00:00'])->save();

        $this->travelTo('2026-07-27 12:00:00');

        $response = $this->getJson('/api/beheer/changelog/maanden');

        $response->assertOk();
        $this->assertSame(['2026-07', '2026-05'], $response->json('data'));
    }

    public function test_maanden_sluit_vermeldingen_buiten_bewaartermijn_uit(): void
    {
        $this->admin();

        $recent = ChangeLog::create(['actie' => 'Recent', 'model' => 'User']);
        $recent->forceFill(['created_at' => now()->subMonths(2)])->save();

        $verlopen = ChangeLog::create(['actie' => 'Te oud', 'model' => 'User']);
        $verlopen->forceFill(['created_at' => now()->subMonths(7)])->save();

        $response = $this->getJson('/api/beheer/changelog/maanden');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame(now()->subMonths(2)->format('Y-m'), $response->json('data.0'));
    }

    public function test_maanden_vereist_authenticatie(): void
    {
        $this->getJson('/api/beheer/changelog/maanden')->assertStatus(401);
    }
}

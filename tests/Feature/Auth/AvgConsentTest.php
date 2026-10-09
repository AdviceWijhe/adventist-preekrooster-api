<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Option;
use App\Models\Role;
use App\Models\User;
use App\Services\Instellingen\InstellingenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AvgConsentTest extends TestCase
{
    use RefreshDatabase;

    private function enableAvg(int $dagen = 14): void
    {
        Option::setValue(InstellingenService::KEY_AVG_ENABLED, '1');
        Option::setValue(InstellingenService::KEY_AVG_DAGEN, (string) $dagen);
        Option::setValue(
            InstellingenService::KEY_AVG_TEKST_AKKOORD,
            'Akkoordtekst e-mail en telefoon.'
        );
        Option::setValue(
            InstellingenService::KEY_AVG_TEKST_WEIGERING,
            'Weigering: account binnen {dagen} dagen inactief.'
        );
    }

    private function gebruiker(array $attrs = []): User
    {
        $user = User::factory()->create(array_merge([
            'active' => true,
            'telefoonnummer' => '0612345678',
        ], $attrs));
        $user->roles()->attach(Role::query()->firstOrCreate(['slug' => 'gebruiker'], ['naam' => 'Gebruiker']));

        return $user;
    }

    public function test_me_toont_avg_vereist_wanneer_enabled_zonder_consent(): void
    {
        $this->enableAvg();
        $user = $this->gebruiker();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('avg.vereist', true)
            ->assertJsonPath('avg.geweigerd', false)
            ->assertJsonPath('avg.dagen_termijn', 14)
            ->assertJsonPath('avg.tekst_akkoord', 'Akkoordtekst e-mail en telefoon.');
    }

    public function test_akkoord_zonder_telefoon_wordt_geweigerd(): void
    {
        $this->enableAvg();
        $user = $this->gebruiker(['telefoonnummer' => null, 'mobiel' => null]);
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $this->postJson('/api/auth/avg-consent', ['akkoord' => true])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['akkoord']);

        $this->assertNull($user->fresh()->avg_consent_at);
    }

    public function test_akkoord_met_mobiel_zet_consent(): void
    {
        $this->enableAvg();
        $user = $this->gebruiker(['telefoonnummer' => null, 'mobiel' => '0687654321']);
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $this->postJson('/api/auth/avg-consent', ['akkoord' => true])
            ->assertOk()
            ->assertJsonPath('avg.vereist', false);

        $this->assertNotNull($user->fresh()->avg_consent_at);
    }

    public function test_stale_consent_zonder_telefoon_is_nog_vereist(): void
    {
        $this->enableAvg();
        $user = $this->gebruiker([
            'telefoonnummer' => null,
            'mobiel' => null,
            'avg_consent_at' => now()->subDay(),
        ]);
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('avg.vereist', true);
    }

    public function test_profiel_leegmaken_telefoon_trekt_consent_in(): void
    {
        Mail::fake();
        $this->enableAvg();
        $user = $this->gebruiker([
            'avg_consent_at' => now()->subDay(),
            'telefoonnummer' => '0612345678',
        ]);
        $user->roles()->attach(Role::query()->firstOrCreate(['slug' => 'predikant'], ['naam' => 'Predikant']));
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $this->putJson('/api/predikant/profiel', [
            'telefoonnummer' => '',
            'mobiel' => '',
        ])
            ->assertOk()
            ->assertJsonPath('data.avg_consent_at', null);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('avg.vereist', true);
    }

    public function test_akkoord_zet_consent_en_wist_weigering(): void
    {
        $this->enableAvg();
        $user = $this->gebruiker();
        $user->forceFill(['avg_refused_at' => now()->subDay()])->save();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $this->postJson('/api/auth/avg-consent', ['akkoord' => true])
            ->assertOk()
            ->assertJsonPath('avg.vereist', false);

        $user->refresh();
        $this->assertNotNull($user->avg_consent_at);
        $this->assertNull($user->avg_refused_at);
    }

    public function test_uitstel_registreert_deferred_at(): void
    {
        $this->enableAvg();
        $user = $this->gebruiker();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $this->postJson('/api/auth/avg-consent/defer')
            ->assertOk()
            ->assertJsonPath('avg.vereist', true)
            ->assertJsonPath('avg.geweigerd', false);

        $this->assertNotNull($user->fresh()->avg_deferred_at);
    }

    public function test_weigering_start_countdown_eenmaal(): void
    {
        $this->enableAvg(10);
        $user = $this->gebruiker();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $this->postJson('/api/auth/avg-consent', ['akkoord' => false])
            ->assertOk()
            ->assertJsonPath('avg.vereist', true)
            ->assertJsonPath('avg.geweigerd', true)
            ->assertJsonPath('avg.dagen_resterend', 10);

        $eerste = $user->fresh()->avg_refused_at;
        $this->assertNotNull($eerste);

        $this->travel(2)->days();

        $this->postJson('/api/auth/avg-consent', ['akkoord' => false])
            ->assertOk()
            ->assertJsonPath('avg.dagen_resterend', 8);

        $this->assertTrue($eerste->equalTo($user->fresh()->avg_refused_at));
    }

    public function test_job_deactiveert_na_termijn(): void
    {
        $this->enableAvg(5);
        $user = $this->gebruiker();
        $user->forceFill(['avg_refused_at' => now()->subDays(6)])->save();

        $this->artisan('gebruikers:verwerk-avg-weigering')->assertSuccessful();

        $this->assertFalse($user->fresh()->active);
    }

    public function test_me_toont_avg_teksten_in_accounttaal(): void
    {
        $this->enableAvg();
        Option::setValue(InstellingenService::KEY_AVG_TEKST_AKKOORD_EN, 'English consent text.');
        Option::setValue(InstellingenService::KEY_AVG_TITEL_EN, 'English title');

        $user = $this->gebruiker();
        $user->forceFill(['taal' => 'en'])->save();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('avg.taal', 'en')
            ->assertJsonPath('avg.titel', 'English title')
            ->assertJsonPath('avg.tekst_akkoord', 'English consent text.');
    }

    public function test_me_trekt_stale_consent_in_zonder_telefoon(): void
    {
        $this->enableAvg();
        $user = $this->gebruiker([
            'avg_consent_at' => now()->subDay(),
            'telefoonnummer' => null,
            'mobiel' => null,
        ]);
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('avg.vereist', true)
            ->assertJsonPath('user.avg_consent_at', null);

        $this->assertNull($user->fresh()->avg_consent_at);
    }

    public function test_admin_heeft_geen_avg_popup(): void
    {
        $this->enableAvg();
        $admin = User::factory()->create(['active' => true]);
        $admin->roles()->attach(Role::query()->firstOrCreate(['slug' => 'admin'], ['naam' => 'Administrator']));
        $this->actingAs($admin);
        session(['two_factor_verified' => true]);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('avg.vereist', false);
    }
}

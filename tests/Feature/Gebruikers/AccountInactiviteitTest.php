<?php

declare(strict_types=1);

namespace Tests\Feature\Gebruikers;

use App\Mail\AccountInactiviteitWaarschuwingMail;
use App\Models\Option;
use App\Models\Role;
use App\Models\User;
use App\Services\Instellingen\InstellingenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AccountInactiviteitTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_stuurt_waarschuwing_en_deactiveert_account(): void
    {
        Mail::fake();

        Option::setValue(InstellingenService::KEY_INACTIVITEIT_ENABLED, '1');
        Option::setValue(InstellingenService::KEY_INACTIVITEIT_DAGEN, '100');
        Option::setValue(InstellingenService::KEY_INACTIVITEIT_WAARSCHUWING_DAGEN, '14');

        $predikant = User::factory()->create([
            'active' => true,
            'last_login_at' => now()->subDays(90),
        ]);
        $predikant->roles()->attach(Role::query()->firstOrCreate(['slug' => 'predikant'], ['naam' => 'Predikant']));

        $teDeactiveren = User::factory()->create([
            'active' => true,
            'last_login_at' => now()->subDays(120),
        ]);
        $teDeactiveren->roles()->attach(Role::query()->firstOrCreate(['slug' => 'predikant'], ['naam' => 'Predikant']));

        $admin = User::factory()->create([
            'active' => true,
            'last_login_at' => now()->subDays(200),
        ]);
        $admin->roles()->attach(Role::query()->firstOrCreate(['slug' => 'admin'], ['naam' => 'Administrator']));

        $this->artisan('gebruikers:verwerk-inactiviteit')->assertSuccessful();

        Mail::assertSent(AccountInactiviteitWaarschuwingMail::class, fn ($mail) => $mail->hasTo($predikant->email));
        $this->assertNotNull($predikant->fresh()->inactiviteit_waarschuwing_at);
        $this->assertFalse($teDeactiveren->fresh()->active);
        $this->assertTrue($admin->fresh()->active);
    }

    public function test_deactivatie_door_command_invalideert_sessies(): void
    {
        config(['session.driver' => 'database']);

        Option::setValue(InstellingenService::KEY_INACTIVITEIT_ENABLED, '1');
        Option::setValue(InstellingenService::KEY_INACTIVITEIT_DAGEN, '100');
        Option::setValue(InstellingenService::KEY_INACTIVITEIT_WAARSCHUWING_DAGEN, '14');

        $teDeactiveren = User::factory()->create([
            'active' => true,
            'last_login_at' => now()->subDays(120),
            'remember_token' => 'cron-token-abcdefghijklmnop',
        ]);
        $teDeactiveren->roles()->attach(Role::query()->firstOrCreate(['slug' => 'predikant'], ['naam' => 'Predikant']));

        DB::table('sessions')->insert([
            'id' => 'sessie-cron-1',
            'user_id' => $teDeactiveren->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => 'payload',
            'last_activity' => time(),
        ]);

        $this->artisan('gebruikers:verwerk-inactiviteit')->assertSuccessful();

        $this->assertFalse($teDeactiveren->fresh()->active);
        $this->assertDatabaseMissing('sessions', ['user_id' => $teDeactiveren->id]);
        $this->assertNotSame('cron-token-abcdefghijklmnop', $teDeactiveren->fresh()->remember_token);
    }

    public function test_login_reset_waarschuwing_timestamp(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('secret123'),
            'inactiviteit_waarschuwing_at' => now()->subDay(),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ])->assertOk();

        $this->assertNull($user->fresh()->inactiviteit_waarschuwing_at);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Beheer;

use App\Mail\TestMailDiagnostics;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MailControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->firstOrCreate(['slug' => 'admin'], ['naam' => 'Administrator']));

        return $user;
    }

    private function beheerder(): User
    {
        Role::query()->firstOrCreate(['slug' => 'admin'], ['naam' => 'Administrator']);
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->firstOrCreate(['slug' => 'beheerder'], ['naam' => 'Beheerder']));

        return $user;
    }

    public function test_verstuur_rooster_endpoint_geeft_fout_terug_bij_artisan_failure(): void
    {
        $user = $this->admin();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        Artisan::shouldReceive('call')
            ->once()
            ->with('rooster:mail-versturen', ['periode' => '2026-05'])
            ->andReturn(1);
        Artisan::shouldReceive('output')->once()->andReturn("Er ging iets mis.\nSMTP smtp://secret-host failed\n");

        $response = $this->postJson('/api/beheer/mail/rooster', ['periode' => '2026-05']);

        $response->assertStatus(500)
            ->assertJson([
                'message' => 'Versturen van rooster voor 2026-05 mislukt.',
            ])
            ->assertJsonMissingPath('output');

        $this->assertStringNotContainsString('secret-host', (string) $response->getContent());
    }

    public function test_mail_test_endpoint_lekt_geen_exception_details(): void
    {
        config(['mail.test_delivery_mode' => 'sync']);

        $user = $this->admin();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        Mail::shouldReceive('to')
            ->once()
            ->andThrow(new \RuntimeException('Connection refused smtp://secret-host:587'));

        $response = $this->postJson('/api/beheer/mail/test', ['email' => 'test@example.com']);

        $response->assertStatus(500)
            ->assertJsonPath('message', __('api.beheer.test_mail_failed'))
            ->assertJsonPath('data.mode', 'sync')
            ->assertJsonMissingPath('data.error');

        $this->assertStringNotContainsString('secret-host', (string) $response->getContent());
    }

    public function test_mail_diagnostics_endpoint_vereist_authenticatie(): void
    {
        $this->getJson('/api/beheer/mail/diagnostics')
            ->assertStatus(401);
    }

    public function test_mail_diagnostics_endpoint_geeft_checks_terug_voor_beheerder(): void
    {
        $user = $this->admin();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $response = $this->getJson('/api/beheer/mail/diagnostics');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'checks' => [
                        'config_presence' => ['status', 'message'],
                        'smtp_connectivity' => ['status', 'message'],
                        'mailer_runtime' => ['status', 'message'],
                        'queue_status' => ['status', 'message'],
                        'dns_check' => ['status', 'message'],
                        'branding_presence' => ['status', 'message'],
                    ],
                    'meta' => ['mail_mode', 'app_env'],
                ],
            ]);
    }

    public function test_mail_test_endpoint_valideert_email_verplicht_en_geldig(): void
    {
        $user = $this->admin();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $this->postJson('/api/beheer/mail/test', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        $this->postJson('/api/beheer/mail/test', ['email' => 'geen-email'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_mail_test_endpoint_verstuurt_direct_buiten_productie(): void
    {
        config(['mail.test_delivery_mode' => 'sync']);
        Mail::fake();

        $user = $this->admin();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $response = $this->postJson('/api/beheer/mail/test', ['email' => 'test@example.com']);

        $response->assertOk()
            ->assertJsonPath('data.mode', 'sync');

        Mail::assertSent(TestMailDiagnostics::class, function (TestMailDiagnostics $mail): bool {
            return $mail->hasTo('test@example.com');
        });
    }

    public function test_mail_test_endpoint_queued_in_productie(): void
    {
        config(['mail.test_delivery_mode' => 'queue']);
        Mail::fake();

        $user = $this->admin();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $response = $this->postJson('/api/beheer/mail/test', ['email' => 'test@example.com']);

        $response->assertOk()
            ->assertJsonPath('data.mode', 'queue');

        Mail::assertQueued(TestMailDiagnostics::class, function (TestMailDiagnostics $mail): bool {
            return $mail->hasTo('test@example.com');
        });
    }

    public function test_mail_diagnostics_endpoint_vereist_admin(): void
    {
        $user = $this->beheerder();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $this->getJson('/api/beheer/mail/diagnostics')
            ->assertStatus(403);
    }

    public function test_mail_test_endpoint_vereist_admin(): void
    {
        Mail::fake();
        $user = $this->beheerder();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $this->postJson('/api/beheer/mail/test', ['email' => 'test@example.com'])
            ->assertStatus(403);

        Mail::assertNothingSent();
    }

    public function test_mail_test_endpoint_is_geratelimiteerd(): void
    {
        config(['mail.test_delivery_mode' => 'sync']);
        Mail::fake();

        $user = $this->admin();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/beheer/mail/test', ['email' => 'test@example.com'])
                ->assertOk();
        }

        $this->postJson('/api/beheer/mail/test', ['email' => 'test@example.com'])
            ->assertStatus(429);
    }
}

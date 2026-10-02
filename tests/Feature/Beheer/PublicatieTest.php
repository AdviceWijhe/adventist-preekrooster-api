<?php

declare(strict_types=1);

namespace Tests\Feature\Beheer;

use App\Mail\PublicatieBekendmakingMail;
use App\Models\Publicatie;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PublicatieTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->firstOrCreate(['slug' => 'admin'], ['naam' => 'Administrator']));

        return $user;
    }

    public function test_admin_kan_rooster_handmatig_publiceren(): void
    {
        Mail::fake();
        $user = $this->admin();
        $contact = User::factory()->create(['email' => 'contact@example.test', 'active' => true]);
        $contact->roles()->attach(Role::query()->firstOrCreate(['slug' => 'contactpersoon'], ['naam' => 'Contactpersoon']));
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $response = $this->postJson('/api/beheer/publicatie', ['periode' => '2026-06']);

        $response->assertStatus(200);
        $this->assertDatabaseHas('publicaties', ['periode' => '2026-06', 'gepubliceerd' => true]);
        Mail::assertSent(PublicatieBekendmakingMail::class, fn (PublicatieBekendmakingMail $mail): bool => $mail->hasTo('contact@example.test'));
    }

    public function test_artisan_commando_publiceert_huidige_maand(): void
    {
        $this->artisan('rooster:publiceer')->assertSuccessful();

        $periode = now()->format('Y-m');
        $this->assertDatabaseHas('publicaties', ['periode' => $periode, 'gepubliceerd' => true]);
    }

    public function test_admin_kan_gepubliceerde_maand_depubliceren(): void
    {
        $user = $this->admin();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $gepubliceerdOp = now()->subDay();
        Publicatie::query()->create([
            'gemeente_id' => null,
            'periode' => '2026-06',
            'gepubliceerd' => true,
            'gepubliceerd_op' => $gepubliceerdOp,
            'gepubliceerd_door' => $user->id,
        ]);

        $response = $this->deleteJson('/api/beheer/publicatie/2026-06');

        $response->assertOk();
        $this->assertDatabaseHas('publicaties', [
            'periode' => '2026-06',
            'gemeente_id' => null,
            'gepubliceerd' => false,
            'gepubliceerd_door' => $user->id,
        ]);
        $this->assertEquals(
            $gepubliceerdOp->toDateTimeString(),
            Publicatie::query()->where('periode', '2026-06')->value('gepubliceerd_op'),
        );
    }

    public function test_depubliceren_van_ontbrekende_periode_geeft_404(): void
    {
        $this->actingAs($this->admin());
        session(['two_factor_verified' => true]);

        $this->deleteJson('/api/beheer/publicatie/2026-07')->assertNotFound();
    }

    public function test_automatische_publicatie_zet_gedepubliceerde_maand_weer_aan(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15', 'Europe/Amsterdam'));
        config(['rooster.automatische_publicatie.enabled' => true]);

        Publicatie::query()->create([
            'gemeente_id' => null,
            'periode' => '2026-06',
            'gepubliceerd' => false,
            'gepubliceerd_op' => now()->subDay(),
        ]);

        $this->artisan('rooster:publiceer-automatisch')->assertSuccessful();

        $this->assertDatabaseHas('publicaties', [
            'periode' => '2026-06',
            'gemeente_id' => null,
            'gepubliceerd' => true,
        ]);

        Carbon::setTestNow();
    }
}

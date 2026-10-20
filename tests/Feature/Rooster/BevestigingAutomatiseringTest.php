<?php

declare(strict_types=1);

namespace Tests\Feature\Rooster;

use App\Mail\SpreekbeurtAutoAnnuleringMail;
use App\Models\Dienst;
use App\Models\Gemeente;
use App\Models\Role;
use App\Models\Spreekbeurt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BevestigingAutomatiseringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-04-15', 'Europe/Amsterdam'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_publiceer_automatisch_publiceert_huidige_en_volgende_maand(): void
    {
        config(['rooster.automatische_publicatie.enabled' => true]);

        $this->artisan('rooster:publiceer-automatisch')->assertSuccessful();

        $this->assertDatabaseHas('publicaties', [
            'periode' => '2026-04',
            'gemeente_id' => null,
            'gepubliceerd' => true,
        ]);
        $this->assertDatabaseHas('publicaties', [
            'periode' => '2026-05',
            'gemeente_id' => null,
            'gepubliceerd' => true,
        ]);
    }

    public function test_publiceer_automatisch_publiceert_volgende_maand_niet_voor_de_tiende(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-09', 'Europe/Amsterdam'));
        config(['rooster.automatische_publicatie.enabled' => true]);

        $this->artisan('rooster:publiceer-automatisch')->assertSuccessful();

        $this->assertDatabaseHas('publicaties', [
            'periode' => '2026-04',
            'gemeente_id' => null,
            'gepubliceerd' => true,
        ]);
        $this->assertDatabaseMissing('publicaties', ['periode' => '2026-05']);
    }

    public function test_publiceer_automatisch_publiceert_volgende_maand_vanaf_de_tiende(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-10', 'Europe/Amsterdam'));
        config(['rooster.automatische_publicatie.enabled' => true]);

        $this->artisan('rooster:publiceer-automatisch')->assertSuccessful();

        $this->assertDatabaseHas('publicaties', [
            'periode' => '2026-04',
            'gemeente_id' => null,
            'gepubliceerd' => true,
        ]);
        $this->assertDatabaseHas('publicaties', [
            'periode' => '2026-05',
            'gemeente_id' => null,
            'gepubliceerd' => true,
        ]);
    }

    public function test_publiceer_automatisch_doet_niets_wanneer_uitgeschakeld(): void
    {
        config(['rooster.automatische_publicatie.enabled' => false]);

        $this->artisan('rooster:publiceer-automatisch')->assertSuccessful();

        $this->assertDatabaseMissing('publicaties', ['periode' => '2026-04']);
    }

    public function test_annuleer_openstaand_verwijdert_onbevestigde_beurten_binnen_venster(): void
    {
        Mail::fake();
        config([
            'rooster.auto_annuleer.enabled' => true,
            'rooster.auto_annuleer.dagen_vooraf' => 7,
        ]);

        $beheerder = User::factory()->create(['email' => 'beheerder@example.test']);
        $beheerder->roles()->attach(Role::query()->firstOrCreate(['slug' => 'beheerder'], ['naam' => 'Beheerder']));
        $admin = User::factory()->create(['email' => 'admin@example.test']);
        $admin->roles()->attach(Role::query()->firstOrCreate(['slug' => 'admin'], ['naam' => 'Admin']));
        $contact = User::factory()->create(['email' => 'contact@example.test']);
        $gemeente = Gemeente::factory()->create(['contactpersoon_id' => $contact->id]);
        $dienst = Dienst::factory()->create([
            'gemeente_id' => $gemeente->id,
            'datum' => '2026-04-20',
        ]);
        $spreker = User::factory()->create(['email' => 'spreker@example.test']);
        $spreekbeurt = Spreekbeurt::factory()->create([
            'dienst_id' => $dienst->id,
            'spreker_id' => $spreker->id,
            'bevestigd' => null,
        ]);

        $this->artisan('rooster:annuleer-openstaand')->assertSuccessful();

        $this->assertDatabaseMissing('spreekbeurten', ['id' => $spreekbeurt->id]);
        Mail::assertSent(SpreekbeurtAutoAnnuleringMail::class, fn (SpreekbeurtAutoAnnuleringMail $mail): bool => $mail->hasTo('spreker@example.test'));
        Mail::assertSent(SpreekbeurtAutoAnnuleringMail::class, fn (SpreekbeurtAutoAnnuleringMail $mail): bool => $mail->hasTo('contact@example.test'));
        Mail::assertNotSent(SpreekbeurtAutoAnnuleringMail::class, fn (SpreekbeurtAutoAnnuleringMail $mail): bool => $mail->hasTo('beheerder@example.test'));
        Mail::assertNotSent(SpreekbeurtAutoAnnuleringMail::class, fn (SpreekbeurtAutoAnnuleringMail $mail): bool => $mail->hasTo('admin@example.test'));
    }

    public function test_annuleer_openstaand_laat_bevestigde_beurten_staan(): void
    {
        config([
            'rooster.auto_annuleer.enabled' => true,
            'rooster.auto_annuleer.dagen_vooraf' => 7,
        ]);

        $gemeente = Gemeente::factory()->create();
        $dienst = Dienst::factory()->create([
            'gemeente_id' => $gemeente->id,
            'datum' => '2026-04-20',
        ]);
        $spreekbeurt = Spreekbeurt::factory()->create([
            'dienst_id' => $dienst->id,
            'bevestigd' => 1,
        ]);

        $this->artisan('rooster:annuleer-openstaand')->assertSuccessful();

        $this->assertDatabaseHas('spreekbeurten', ['id' => $spreekbeurt->id]);
    }

    public function test_annuleer_openstaand_doet_niets_wanneer_uitgeschakeld(): void
    {
        config(['rooster.auto_annuleer.enabled' => false]);

        $gemeente = Gemeente::factory()->create();
        $dienst = Dienst::factory()->create([
            'gemeente_id' => $gemeente->id,
            'datum' => '2026-04-20',
        ]);
        $spreekbeurt = Spreekbeurt::factory()->create([
            'dienst_id' => $dienst->id,
            'bevestigd' => null,
        ]);

        $this->artisan('rooster:annuleer-openstaand')->assertSuccessful();

        $this->assertDatabaseHas('spreekbeurten', ['id' => $spreekbeurt->id]);
    }
}

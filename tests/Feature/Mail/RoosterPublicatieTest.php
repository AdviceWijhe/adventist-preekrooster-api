<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Mail\RoosterPublicatie;
use App\Models\Dienst;
use App\Models\Gemeente;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RoosterPublicatieTest extends TestCase
{
    use RefreshDatabase;

    public function test_rooster_mail_wordt_verstuurd_naar_actieve_contactpersonen(): void
    {
        Mail::fake();

        $contactpersoonRole = Role::query()->firstOrCreate(
            ['slug' => 'contactpersoon'],
            ['naam' => 'Contactpersoon']
        );

        $actieveGemeente = Gemeente::factory()->create(['active' => true]);

        $actieveContactpersoon = User::factory()->create(['email' => 'actief@example.test', 'active' => true]);
        $actieveContactpersoon->roles()->attach($contactpersoonRole, ['gemeente_id' => $actieveGemeente->id]);

        $inactieveContactpersoon = User::factory()->create(['email' => 'inactief@example.test', 'active' => false]);
        $inactieveContactpersoon->roles()->attach($contactpersoonRole, ['gemeente_id' => $actieveGemeente->id]);

        Dienst::factory()->create([
            'datum' => '2026-05-10',
            'gemeente_id' => $actieveGemeente->id,
            'type' => 'reguliere_dienst',
        ]);
        Dienst::factory()->create([
            'datum' => '2026-05-17',
            'gemeente_id' => $actieveGemeente->id,
            'type' => 'reguliere_dienst',
        ]);

        $this->artisan('rooster:mail-versturen 2026-05')->assertSuccessful();

        Mail::assertSent(
            RoosterPublicatie::class,
            fn (RoosterPublicatie $mail): bool => $mail->hasTo('actief@example.test')
                && $mail->periode === '2026-05'
                && $mail->diensten->count() === 2
        );

        Mail::assertNotSent(
            RoosterPublicatie::class,
            fn (RoosterPublicatie $mail): bool => $mail->hasTo('inactief@example.test')
        );
    }

    public function test_rooster_mail_wordt_niet_verstuurd_naar_contactpersoon_van_inactieve_gemeente(): void
    {
        Mail::fake();

        $contactpersoonRole = Role::query()->firstOrCreate(
            ['slug' => 'contactpersoon'],
            ['naam' => 'Contactpersoon']
        );

        $inactieveGemeente = Gemeente::factory()->create(['active' => false]);
        $contactpersoon = User::factory()->create(['email' => 'inactieve-gemeente@example.test', 'active' => true]);
        $contactpersoon->roles()->attach($contactpersoonRole, ['gemeente_id' => $inactieveGemeente->id]);

        Dienst::factory()->create([
            'datum' => '2026-05-10',
            'gemeente_id' => $inactieveGemeente->id,
        ]);

        $this->artisan('rooster:mail-versturen 2026-05')->assertSuccessful();

        Mail::assertNotSent(
            RoosterPublicatie::class,
            fn (RoosterPublicatie $mail): bool => $mail->hasTo('inactieve-gemeente@example.test')
        );
    }

    public function test_rooster_mail_commando_valideert_periode_formaat(): void
    {
        $this->artisan('rooster:mail-versturen 2026/05')
            ->expectsOutput('Ongeldige periode. Gebruik YYYY-MM formaat.')
            ->assertExitCode(1);
    }
}

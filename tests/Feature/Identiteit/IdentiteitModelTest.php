<?php

declare(strict_types=1);

namespace Tests\Feature\Identiteit;

use App\Models\Functie;
use App\Models\Gemeente;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\FunctieSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdentiteitModelTest extends TestCase
{
    use RefreshDatabase;

    private function functie(string $slug, string $naam): Functie
    {
        return Functie::query()->firstOrCreate(['slug' => $slug], ['naam' => $naam]);
    }

    private function role(string $slug, string $naam): Role
    {
        return Role::query()->firstOrCreate(['slug' => $slug], ['naam' => $naam]);
    }

    public function test_gebruiker_kan_meerdere_functies_hebben(): void
    {
        $user = User::factory()->create();
        $user->functies()->attach([
            $this->functie('spreker', 'Spreker')->id,
            $this->functie('contactpersoon', 'Contactpersoon')->id,
        ]);

        $this->assertTrue($user->hasFunctie('spreker'));
        $this->assertTrue($user->hasFunctie('contactpersoon'));
        $this->assertFalse($user->hasFunctie('predikant'));
        $this->assertTrue($user->hasAnyFunctie(['predikant', 'contactpersoon']));
        $this->assertCount(2, $user->functies);
    }

    public function test_gebruiker_kan_aan_meerdere_gemeentes_gekoppeld_worden(): void
    {
        $user = User::factory()->create();
        $g1 = Gemeente::factory()->create();
        $g2 = Gemeente::factory()->create();
        $g3 = Gemeente::factory()->create();

        $user->gemeentes()->attach([$g1->id, $g2->id, $g3->id]);

        $this->assertCount(3, $user->fresh()->gemeentes);
        $this->assertTrue($g1->fresh()->gebruikers->contains($user));
    }

    public function test_statistieken_toegang_default_false(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->fresh()->statistieken_toegang);
    }

    public function test_heeft_statistiek_toegang_logica(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach($this->role('admin', 'Administrator')->id);
        $this->assertTrue($admin->fresh()->heeftStatistiekToegang());

        $beheerderRol = $this->role('beheerder', 'Beheerder');

        $beheerderZonder = User::factory()->create(['statistieken_toegang' => false]);
        $beheerderZonder->roles()->attach($beheerderRol->id);
        $this->assertFalse($beheerderZonder->fresh()->heeftStatistiekToegang());

        $beheerderMet = User::factory()->create(['statistieken_toegang' => true]);
        $beheerderMet->roles()->attach($beheerderRol->id);
        $this->assertTrue($beheerderMet->fresh()->heeftStatistiekToegang());

        $gewoon = User::factory()->create(['statistieken_toegang' => true]);
        $gewoon->roles()->attach($this->role('gebruiker', 'Gebruiker')->id);
        $this->assertFalse($gewoon->fresh()->heeftStatistiekToegang());
    }

    public function test_functie_seeder_levert_drie_functies(): void
    {
        $this->seed(FunctieSeeder::class);

        $this->assertSame(3, Functie::query()->count());
        foreach (['contactpersoon', 'spreker', 'predikant'] as $slug) {
            $this->assertDatabaseHas('functies', ['slug' => $slug]);
        }
    }
}
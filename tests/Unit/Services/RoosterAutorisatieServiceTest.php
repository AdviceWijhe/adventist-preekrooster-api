<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Functie;
use App\Models\Gemeente;
use App\Models\Role;
use App\Models\User;
use App\Services\RoosterAutorisatieService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoosterAutorisatieServiceTest extends TestCase
{
    use RefreshDatabase;

    private RoosterAutorisatieService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RoosterAutorisatieService;
        foreach (['contactpersoon', 'spreker', 'predikant'] as $slug) {
            Functie::query()->firstOrCreate(['slug' => $slug], ['naam' => ucfirst($slug)]);
        }
        foreach (['admin', 'beheerder', 'gebruiker'] as $slug) {
            Role::query()->firstOrCreate(['slug' => $slug], ['naam' => ucfirst($slug)]);
        }
    }

    public function test_admin_is_globaal_en_ziet_alle_gemeenten(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::query()->where('slug', 'admin')->value('id'));
        $g = Gemeente::factory()->create();

        $this->assertTrue($this->service->isGlobaalBeheerder($admin));
        $this->assertContains($g->id, $this->service->beheerbareGemeenteIds($admin));
        $this->assertTrue($this->service->kanRoosterBeheren($admin, $g->id));
    }

    public function test_contactpersoon_alleen_eigen_gemeente_geen_inschrijf(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', 'gebruiker')->value('id'));
        $user->functies()->attach(Functie::query()->where('slug', 'contactpersoon')->value('id'));
        $eigen = Gemeente::factory()->create(['contactpersoon_id' => $user->id]);
        $ander = Gemeente::factory()->create();

        $this->assertSame([$eigen->id], $this->service->beheerbareGemeenteIds($user));
        $this->assertTrue($this->service->kanRoosterBeheren($user, $eigen->id));
        $this->assertFalse($this->service->kanRoosterBeheren($user, $ander->id));
        $this->assertFalse($this->service->kanZichzelfInschrijven($user));
    }

    public function test_predikant_scope_plus_inschrijf(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', 'gebruiker')->value('id'));
        $user->functies()->attach(Functie::query()->where('slug', 'predikant')->value('id'));
        $eigen = Gemeente::factory()->create(['predikant_id' => $user->id]);

        $this->assertTrue($this->service->kanRoosterBeheren($user, $eigen->id));
        $this->assertTrue($this->service->kanZichzelfInschrijven($user));
    }

    public function test_spreker_alleen_inschrijf(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', 'gebruiker')->value('id'));
        $user->functies()->attach(Functie::query()->where('slug', 'spreker')->value('id'));
        $g = Gemeente::factory()->create();

        $this->assertSame([], $this->service->beheerbareGemeenteIds($user));
        $this->assertFalse($this->service->kanRoosterBeheren($user, $g->id));
        $this->assertTrue($this->service->kanZichzelfInschrijven($user));
    }

    public function test_contactpersoon_zonder_fk_heeft_geen_scope(): void
    {
        $user = User::factory()->create();
        $user->functies()->attach(Functie::query()->where('slug', 'contactpersoon')->value('id'));
        Gemeente::factory()->create();

        $this->assertSame([], $this->service->beheerbareGemeenteIds($user));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Beheer;

use App\Models\Dienst;
use App\Models\Functie;
use App\Models\Gemeente;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoosterScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['contactpersoon', 'spreker', 'predikant'] as $slug) {
            Functie::query()->firstOrCreate(['slug' => $slug], ['naam' => ucfirst($slug)]);
        }
        foreach (['admin', 'beheerder', 'gebruiker'] as $slug) {
            Role::query()->firstOrCreate(['slug' => $slug], ['naam' => ucfirst($slug)]);
        }
    }

    private function gebruikerRoleId(): int
    {
        return (int) Role::query()->where('slug', 'gebruiker')->value('id');
    }

    private function withTwoFactor(User $user): void
    {
        $this->actingAs($user);
        session(['two_factor_verified' => true]);
    }

    /** @return list<int> */
    private function gemeenteIdsFromMatrix(array $payload): array
    {
        return collect($payload['districts'] ?? [])
            ->flatMap(fn (array $district) => collect($district['gemeentes'] ?? [])->pluck('id'))
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    public function test_contactpersoon_matrix_toont_alleen_eigen_gemeente(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach($this->gebruikerRoleId());
        $user->functies()->attach(Functie::query()->where('slug', 'contactpersoon')->value('id'));

        $eigen = Gemeente::factory()->create(['active' => true, 'contactpersoon_id' => $user->id]);
        $ander = Gemeente::factory()->create(['active' => true]);
        Dienst::factory()->create([
            'datum' => '2026-06-06',
            'gemeente_id' => $eigen->id,
            'type' => 'sabbatschool',
        ]);
        Dienst::factory()->create([
            'datum' => '2026-06-06',
            'gemeente_id' => $ander->id,
            'type' => 'sabbatschool',
        ]);

        $this->withTwoFactor($user);

        $response = $this->getJson('/api/beheer/roosters/matrix?maand=2026-06');

        $response->assertOk();
        $ids = $this->gemeenteIdsFromMatrix($response->json());
        $this->assertSame([$eigen->id], $ids);
    }

    public function test_contactpersoon_kan_andere_gemeente_dienst_niet_bewerken(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach($this->gebruikerRoleId());
        $user->functies()->attach(Functie::query()->where('slug', 'contactpersoon')->value('id'));

        Gemeente::factory()->create(['active' => true, 'contactpersoon_id' => $user->id]);
        $ander = Gemeente::factory()->create(['active' => true]);
        $dienst = Dienst::factory()->create([
            'datum' => '2026-06-06',
            'gemeente_id' => $ander->id,
            'type' => 'sabbatschool',
        ]);

        $this->withTwoFactor($user);

        $response = $this->putJson("/api/beheer/roosters/{$dienst->id}", [
            'datum' => '2026-06-06',
            'gemeente_id' => $ander->id,
            'type' => 'sabbatschool',
        ]);

        $response->assertForbidden();
    }

    public function test_predikant_kan_eigen_gemeente_dienst_bewerken(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach($this->gebruikerRoleId());
        $user->functies()->attach(Functie::query()->where('slug', 'predikant')->value('id'));

        $eigen = Gemeente::factory()->create(['active' => true, 'predikant_id' => $user->id]);
        $dienst = Dienst::factory()->create([
            'datum' => '2026-06-06',
            'gemeente_id' => $eigen->id,
            'type' => 'sabbatschool',
            'eigeninvulling' => 'oud',
        ]);

        $this->withTwoFactor($user);

        $response = $this->putJson("/api/beheer/roosters/{$dienst->id}", [
            'datum' => '2026-06-06',
            'gemeente_id' => $eigen->id,
            'type' => 'sabbatschool',
            'eigeninvulling' => 'nieuw',
        ]);

        $response->assertOk();
        $this->assertSame('nieuw', $dienst->fresh()->eigeninvulling);
    }

    public function test_spreker_krijgt_403_op_matrix(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach($this->gebruikerRoleId());
        $user->functies()->attach(Functie::query()->where('slug', 'spreker')->value('id'));

        Gemeente::factory()->create(['active' => true]);

        $this->withTwoFactor($user);

        $response = $this->getJson('/api/beheer/roosters/matrix?maand=2026-06');

        $response->assertForbidden();
    }

    public function test_admin_matrix_toont_alle_gemeenten(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::query()->where('slug', 'admin')->value('id'));

        $g1 = Gemeente::factory()->create(['active' => true]);
        $g2 = Gemeente::factory()->create(['active' => true]);
        Dienst::factory()->create([
            'datum' => '2026-06-06',
            'gemeente_id' => $g1->id,
            'type' => 'sabbatschool',
        ]);
        Dienst::factory()->create([
            'datum' => '2026-06-06',
            'gemeente_id' => $g2->id,
            'type' => 'sabbatschool',
        ]);

        $this->withTwoFactor($admin);

        $response = $this->getJson('/api/beheer/roosters/matrix?maand=2026-06');

        $response->assertOk();
        $ids = $this->gemeenteIdsFromMatrix($response->json());
        $this->assertContains($g1->id, $ids);
        $this->assertContains($g2->id, $ids);
    }
}

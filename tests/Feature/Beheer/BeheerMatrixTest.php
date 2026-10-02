<?php

declare(strict_types=1);

namespace Tests\Feature\Beheer;

use App\Models\Dienst;
use App\Models\District;
use App\Models\Gemeente;
use App\Models\Role;
use App\Models\Spreekbeurt;
use App\Models\User;
use App\Services\RoosterMatrixService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BeheerMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function beheerder(): User
    {
        $user = User::factory()->create();
        $role = Role::query()->firstOrCreate(
            ['slug' => 'beheerder'],
            ['naam' => 'Beheerder']
        );
        $user->roles()->attach($role);

        return $user;
    }

    public function test_beheer_standaand_maand_endpoint(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-15', RoosterMatrixService::TIMEZONE));

        $user = $this->beheerder();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $response = $this->getJson('/api/beheer/roosters/standaard-maand');

        $response->assertOk();
        $this->assertArrayHasKey('maand', $response->json());
        $this->assertArrayHasKey('eerste_maand', $response->json());
        $this->assertArrayHasKey('laatste_maand', $response->json());
        $this->assertSame('2026-04', $response->json('maand'));
    }

    public function test_beheer_standaard_maand_schakelt_na_laatste_zaterdag(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-27', RoosterMatrixService::TIMEZONE));

        $user = $this->beheerder();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $response = $this->getJson('/api/beheer/roosters/standaard-maand');

        $response->assertOk()->assertJsonPath('maand', '2026-08');
    }

    public function test_beheer_standaard_maand_blijft_huidige_maand_ook_met_toekomst_diensten(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-15', RoosterMatrixService::TIMEZONE));

        $gemeente = Gemeente::factory()->create(['active' => true]);
        Dienst::factory()->create([
            'datum' => '2030-07-07',
            'gemeente_id' => $gemeente->id,
            'type' => 'sabbatschool',
        ]);

        $user = $this->beheerder();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $response = $this->getJson('/api/beheer/roosters/standaard-maand');

        $response->assertOk();
        $this->assertSame('2026-04', $response->json('maand'));
        $this->assertSame('2030-07', $response->json('laatste_maand'));
    }

    public function test_beheer_matrix_tonen_zonder_publicatiecheck(): void
    {
        $gemeente = Gemeente::factory()->create(['active' => true]);
        Dienst::factory()->create([
            'datum' => '2026-06-06',
            'gemeente_id' => $gemeente->id,
            'type' => 'sabbatschool',
        ]);

        $user = $this->beheerder();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $response = $this->getJson('/api/beheer/roosters/matrix?maand=2026-06');

        $response->assertOk()
            ->assertJsonPath('gepubliceerd', true);
        $this->assertNotEmpty($response->json('saturdays'));
    }

    public function test_beheer_matrix_bevat_spreker_status_per_dienstregel(): void
    {
        $gemeente = Gemeente::factory()->create(['active' => true]);
        $spreker = User::factory()->create();
        $dienst = Dienst::factory()->create([
            'datum' => '2026-06-06',
            'gemeente_id' => $gemeente->id,
            'type' => 'sabbatschool',
        ]);
        Spreekbeurt::factory()->create([
            'dienst_id' => $dienst->id,
            'spreker_id' => $spreker->id,
            'bevestigd' => 1,
        ]);
        $user = $this->beheerder();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $response = $this->getJson('/api/beheer/roosters/matrix?maand=2026-06');

        $response->assertOk();
        $response->assertJsonFragment(['spreker_status' => 'bevestigd']);
    }

    public function test_beheer_matrix_verbergt_gemeentes_van_onzichtbaar_district(): void
    {
        $zichtbaar = District::factory()->create(['naam' => 'Zichtbaar District', 'visible' => true]);
        $verborgen = District::factory()->create(['naam' => 'Verborgen District', 'visible' => false]);

        Gemeente::factory()->create([
            'naam' => 'Zichtbare Gemeente',
            'district_id' => $zichtbaar->id,
            'active' => true,
        ]);
        Gemeente::factory()->create([
            'naam' => 'Verborgen Gemeente',
            'district_id' => $verborgen->id,
            'active' => true,
        ]);
        Gemeente::factory()->create([
            'naam' => 'Losse Gemeente',
            'district_id' => null,
            'active' => true,
        ]);

        $user = $this->beheerder();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        $response = $this->getJson('/api/beheer/roosters/matrix?maand=2026-06');

        $response->assertOk();
        $response->assertJsonFragment(['naam' => 'Zichtbare Gemeente']);
        $response->assertJsonFragment(['naam' => 'Zichtbaar District']);
        $response->assertJsonFragment(['naam' => 'Losse Gemeente']);
        $response->assertJsonMissing(['naam' => 'Verborgen Gemeente']);
        $response->assertJsonMissing(['naam' => 'Verborgen District']);
    }
}

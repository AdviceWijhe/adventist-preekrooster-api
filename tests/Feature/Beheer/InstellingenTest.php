<?php

declare(strict_types=1);

namespace Tests\Feature\Beheer;

use App\Models\Option;
use App\Models\Role;
use App\Models\User;
use App\Services\Instellingen\InstellingenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstellingenTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->firstOrCreate(['slug' => 'admin'], ['naam' => 'Administrator']));

        return $user;
    }

    public function test_beheerder_kan_instellingen_ophalen_en_opslaan(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        session(['two_factor_verified' => true]);

        $this->getJson('/api/beheer/instellingen')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'beurt_annuleren' => ['enabled', 'dagen_vooraf'],
                    'inactiviteit' => ['enabled', 'dagen', 'waarschuwing_dagen'],
                    'changelog' => ['bewaartermijn_dagen'],
                    'avg' => [
                        'enabled',
                        'dagen',
                        'titel' => ['nl', 'en'],
                        'tekst_akkoord' => ['nl', 'en'],
                        'tekst_weigering' => ['nl', 'en'],
                    ],
                ],
            ]);

        $this->putJson('/api/beheer/instellingen', [
            'beurt_annuleren' => [
                'enabled' => true,
                'dagen_vooraf' => 14,
            ],
            'inactiviteit' => [
                'enabled' => true,
                'dagen' => 180,
                'waarschuwing_dagen' => 21,
            ],
            'changelog' => [
                'bewaartermijn_dagen' => 90,
            ],
            'avg' => [
                'enabled' => true,
                'dagen' => 21,
                'titel' => ['nl' => 'AVG-titel', 'en' => 'GDPR title'],
                'tekst_akkoord' => ['nl' => 'Akkoord tekst', 'en' => 'Consent text'],
                'tekst_weigering' => ['nl' => 'Weigering over {dagen} dagen', 'en' => 'Refusal in {dagen} days'],
            ],
        ])->assertOk()
            ->assertJsonPath('data.beurt_annuleren.dagen_vooraf', 14)
            ->assertJsonPath('data.inactiviteit.dagen', 180)
            ->assertJsonPath('data.inactiviteit.waarschuwing_dagen', 21)
            ->assertJsonPath('data.changelog.bewaartermijn_dagen', 90)
            ->assertJsonPath('data.avg.enabled', true)
            ->assertJsonPath('data.avg.dagen', 21)
            ->assertJsonPath('data.avg.titel.nl', 'AVG-titel')
            ->assertJsonPath('data.avg.titel.en', 'GDPR title')
            ->assertJsonPath('data.avg.tekst_akkoord.nl', 'Akkoord tekst');

        $this->assertSame('14', Option::getValue(InstellingenService::KEY_BEURT_ANNULEREN_DAGEN));
        $this->assertSame('90', Option::getValue(InstellingenService::KEY_CHANGELOG_BEWAARTERMIJN_DAGEN));
        $this->assertSame('1', Option::getValue(InstellingenService::KEY_AVG_ENABLED));
        $this->assertSame('21', Option::getValue(InstellingenService::KEY_AVG_DAGEN));
        $this->assertSame('AVG-titel', Option::getValue(InstellingenService::KEY_AVG_TITEL));
        $this->assertSame('GDPR title', Option::getValue(InstellingenService::KEY_AVG_TITEL_EN));
    }

    public function test_changelog_bewaartermijn_heeft_ondergrens(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        session(['two_factor_verified' => true]);

        $this->putJson('/api/beheer/instellingen', [
            'changelog' => ['bewaartermijn_dagen' => 1],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['changelog.bewaartermijn_dagen']);
    }

    public function test_waarschuwing_dagen_moet_kleiner_zijn_dan_inactiviteit_dagen(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        session(['two_factor_verified' => true]);

        $this->putJson('/api/beheer/instellingen', [
            'inactiviteit' => [
                'enabled' => true,
                'dagen' => 90,
                'waarschuwing_dagen' => 90,
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['inactiviteit.waarschuwing_dagen']);
    }
}

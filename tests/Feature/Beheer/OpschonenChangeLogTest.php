<?php

declare(strict_types=1);

namespace Tests\Feature\Beheer;

use App\Models\ChangeLog;
use App\Models\Option;
use App\Services\Instellingen\InstellingenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpschonenChangeLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_verwijdert_vermeldingen_ouder_dan_standaard_bewaartermijn(): void
    {
        $oud = ChangeLog::create(['actie' => 'Te oud', 'model' => 'User']);
        $oud->forceFill(['created_at' => now()->subDays(200)])->save();

        $recent = ChangeLog::create(['actie' => 'Recent', 'model' => 'User']);
        $recent->forceFill(['created_at' => now()->subDays(90)])->save();

        $this->artisan('changelog:opschonen')->assertSuccessful();

        $this->assertDatabaseMissing('change_log', ['id' => $oud->id]);
        $this->assertDatabaseHas('change_log', ['id' => $recent->id]);
    }

    public function test_ondersteunt_afwijkend_aantal_dagen(): void
    {
        $vermelding = ChangeLog::create(['actie' => 'Twee dagen oud', 'model' => 'User']);
        $vermelding->forceFill(['created_at' => now()->subDays(2)])->save();

        $this->artisan('changelog:opschonen', ['--dagen' => 1])->assertSuccessful();

        $this->assertDatabaseMissing('change_log', ['id' => $vermelding->id]);
    }

    public function test_gebruikt_ingestelde_bewaartermijn_wanneer_geen_optie_is_opgegeven(): void
    {
        Option::setValue(InstellingenService::KEY_CHANGELOG_BEWAARTERMIJN_DAGEN, '30');

        $vermelding = ChangeLog::create(['actie' => 'Drie weken oud', 'model' => 'User']);
        $vermelding->forceFill(['created_at' => now()->subDays(45)])->save();

        $this->artisan('changelog:opschonen')->assertSuccessful();

        $this->assertDatabaseMissing('change_log', ['id' => $vermelding->id]);
    }
}

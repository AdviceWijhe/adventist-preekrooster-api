<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ChangeLog;
use App\Models\Dienst;
use App\Models\Spreekbeurt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SpreekbeurtObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_changelog_bij_koppeling_bevat_geen_bericht(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        $dienst = Dienst::factory()->create();

        Spreekbeurt::factory()->create([
            'dienst_id' => $dienst->id,
            'spreker_id' => $user->id,
            'bericht' => 'Gevoelige notitie',
            'bevestigd' => null,
        ]);

        $entry = ChangeLog::query()->where('actie', 'Spreker gekoppeld')->latest('id')->first();

        $this->assertNotNull($entry);
        $this->assertIsArray($entry->nieuw);
        $this->assertArrayNotHasKey('bericht', $entry->nieuw);
        $this->assertArrayHasKey('spreker_id', $entry->nieuw);
        $this->assertArrayHasKey('dienst_id', $entry->nieuw);
        $this->assertArrayHasKey('bevestigd', $entry->nieuw);
    }
}

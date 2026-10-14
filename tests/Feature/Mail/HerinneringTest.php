<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Mail\BevestigingsHerinnering;
use App\Models\Dienst;
use App\Models\Role;
use App\Models\Spreekbeurt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class HerinneringTest extends TestCase
{
    use RefreshDatabase;

    public function test_herinnering_wordt_verstuurd_voor_onbevestigde_beurten(): void
    {
        Mail::fake();

        $predikant = User::factory()->create(['email' => 'predikant@adventist.nl']);
        $predikant->roles()->attach(Role::query()->firstOrCreate(['slug' => 'predikant'], ['naam' => 'Predikant']));

        $dienst = Dienst::factory()->create([
            'datum' => now()->addWeek()->format('Y-m-d'),
        ]);

        Spreekbeurt::factory()->create([
            'spreker_id' => $predikant->id,
            'dienst_id' => $dienst->id,
            'bevestigd' => null,
        ]);

        $this->artisan('rooster:herinneringen')->assertSuccessful();

        Mail::assertSent(BevestigingsHerinnering::class, fn (BevestigingsHerinnering $mail): bool => $mail->hasTo('predikant@adventist.nl'));
    }

    public function test_geen_herinnering_voor_reeds_bevestigde_beurten(): void
    {
        Mail::fake();

        $predikant = User::factory()->create();
        $predikant->roles()->attach(Role::query()->firstOrCreate(['slug' => 'predikant'], ['naam' => 'Predikant']));

        $dienst = Dienst::factory()->create([
            'datum' => now()->addWeek()->format('Y-m-d'),
        ]);

        Spreekbeurt::factory()->create([
            'spreker_id' => $predikant->id,
            'dienst_id' => $dienst->id,
            'bevestigd' => 1,
        ]);

        $this->artisan('rooster:herinneringen')->assertSuccessful();

        Mail::assertNotSent(BevestigingsHerinnering::class);
    }

    public function test_geen_herinnering_buiten_standaard_datumvenster(): void
    {
        Mail::fake();

        $predikant = User::factory()->create(['email' => 'predikant-buiten-venster@adventist.nl']);
        $predikant->roles()->attach(Role::query()->firstOrCreate(['slug' => 'predikant'], ['naam' => 'Predikant']));

        $dienst = Dienst::factory()->create([
            'datum' => now()->addWeeks(5)->format('Y-m-d'),
        ]);

        Spreekbeurt::factory()->create([
            'spreker_id' => $predikant->id,
            'dienst_id' => $dienst->id,
            'bevestigd' => null,
        ]);

        $this->artisan('rooster:herinneringen')->assertSuccessful();

        Mail::assertNotSent(BevestigingsHerinnering::class);
    }

    public function test_geen_herinnering_wanneer_feature_uitgeschakeld(): void
    {
        Mail::fake();
        config(['mail.features.bevestigings_herinnering' => false]);

        $predikant = User::factory()->create(['email' => 'predikant@adventist.nl']);
        $predikant->roles()->attach(Role::query()->firstOrCreate(['slug' => 'predikant'], ['naam' => 'Predikant']));

        $dienst = Dienst::factory()->create([
            'datum' => now()->addWeek()->format('Y-m-d'),
        ]);

        Spreekbeurt::factory()->create([
            'spreker_id' => $predikant->id,
            'dienst_id' => $dienst->id,
            'bevestigd' => null,
        ]);

        $this->artisan('rooster:herinneringen')->assertSuccessful();

        Mail::assertNotSent(BevestigingsHerinnering::class);
    }
}

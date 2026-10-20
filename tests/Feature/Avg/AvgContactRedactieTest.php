<?php

declare(strict_types=1);

namespace Tests\Feature\Avg;

use App\Models\Dienst;
use App\Models\Functie;
use App\Models\Gemeente;
use App\Models\Option;
use App\Models\Role;
use App\Models\Spreekbeurt;
use App\Models\User;
use App\Services\Instellingen\InstellingenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvgContactRedactieTest extends TestCase
{
    use RefreshDatabase;

    public function test_contactpersoon_email_wordt_afgeschermd_zonder_consent(): void
    {
        Option::setValue(InstellingenService::KEY_AVG_ENABLED, '1');

        $spreker = User::factory()->create(['active' => true]);
        $spreker->roles()->attach(Role::query()->firstOrCreate(['slug' => 'gebruiker'], ['naam' => 'Gebruiker']));
        $spreker->functies()->attach(Functie::query()->firstOrCreate(['slug' => 'spreker'], ['naam' => 'Spreker']));

        $contact = User::factory()->create([
            'active' => true,
            'email' => 'contact@example.test',
            'telefoonnummer' => '0611111111',
            'mobiel' => '0622222222',
            'avg_consent_at' => null,
        ]);
        $contact->functies()->attach(Functie::query()->firstOrCreate(['slug' => 'contactpersoon'], ['naam' => 'Contactpersoon']));

        $gemeente = Gemeente::factory()->create(['contactpersoon_id' => $contact->id]);
        $dienst = Dienst::factory()->create(['gemeente_id' => $gemeente->id]);
        $beurt = Spreekbeurt::factory()->create([
            'dienst_id' => $dienst->id,
            'spreker_id' => $spreker->id,
            'bevestigd' => 1,
        ]);

        $this->actingAs($spreker);
        session(['two_factor_verified' => true]);

        $this->getJson("/api/predikant/beurten/{$beurt->id}")
            ->assertOk()
            ->assertJsonPath('data.dienst.gemeente.contactpersoon.email', null)
            ->assertJsonPath('data.dienst.gemeente.contactpersoon.telefoonnummer', null)
            ->assertJsonPath('data.dienst.gemeente.contactpersoon.mobiel', null);
    }

    public function test_contactpersoon_afgeschermd_bij_consent_zonder_telefoon(): void
    {
        Option::setValue(InstellingenService::KEY_AVG_ENABLED, '1');

        $spreker = User::factory()->create(['active' => true]);
        $spreker->roles()->attach(Role::query()->firstOrCreate(['slug' => 'gebruiker'], ['naam' => 'Gebruiker']));
        $spreker->functies()->attach(Functie::query()->firstOrCreate(['slug' => 'spreker'], ['naam' => 'Spreker']));

        $contact = User::factory()->create([
            'active' => true,
            'email' => 'contact@example.test',
            'telefoonnummer' => null,
            'mobiel' => null,
            'avg_consent_at' => now(),
        ]);
        $contact->functies()->attach(Functie::query()->firstOrCreate(['slug' => 'contactpersoon'], ['naam' => 'Contactpersoon']));

        $gemeente = Gemeente::factory()->create(['contactpersoon_id' => $contact->id]);
        $dienst = Dienst::factory()->create(['gemeente_id' => $gemeente->id]);
        $beurt = Spreekbeurt::factory()->create([
            'dienst_id' => $dienst->id,
            'spreker_id' => $spreker->id,
            'bevestigd' => 1,
        ]);

        $this->actingAs($spreker);
        session(['two_factor_verified' => true]);

        $this->getJson("/api/predikant/beurten/{$beurt->id}")
            ->assertOk()
            ->assertJsonPath('data.dienst.gemeente.contactpersoon.email', null);
    }

    public function test_contactpersoon_email_zichtbaar_met_consent(): void
    {
        Option::setValue(InstellingenService::KEY_AVG_ENABLED, '1');

        $spreker = User::factory()->create(['active' => true]);
        $spreker->roles()->attach(Role::query()->firstOrCreate(['slug' => 'gebruiker'], ['naam' => 'Gebruiker']));
        $spreker->functies()->attach(Functie::query()->firstOrCreate(['slug' => 'spreker'], ['naam' => 'Spreker']));

        $contact = User::factory()->create([
            'active' => true,
            'email' => 'contact@example.test',
            'telefoonnummer' => '0611111111',
            'avg_consent_at' => now(),
        ]);
        $contact->functies()->attach(Functie::query()->firstOrCreate(['slug' => 'contactpersoon'], ['naam' => 'Contactpersoon']));

        $gemeente = Gemeente::factory()->create(['contactpersoon_id' => $contact->id]);
        $dienst = Dienst::factory()->create(['gemeente_id' => $gemeente->id]);
        $beurt = Spreekbeurt::factory()->create([
            'dienst_id' => $dienst->id,
            'spreker_id' => $spreker->id,
            'bevestigd' => 1,
        ]);

        $this->actingAs($spreker);
        session(['two_factor_verified' => true]);

        $this->getJson("/api/predikant/beurten/{$beurt->id}")
            ->assertOk()
            ->assertJsonPath('data.dienst.gemeente.contactpersoon.email', 'contact@example.test')
            ->assertJsonPath('data.dienst.gemeente.contactpersoon.telefoonnummer', '0611111111');
    }
}

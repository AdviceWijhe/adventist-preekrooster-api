<?php

declare(strict_types=1);

namespace Tests\Feature\Beheer;

use App\Mail\WelkomGebruikerMail;
use App\Models\Option;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MailTemplateControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->firstOrCreate(['slug' => 'admin'], ['naam' => 'Administrator']));

        return $user;
    }

    private function inloggen(): User
    {
        $user = $this->admin();
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        return $user;
    }

    private function beheerder(): User
    {
        $user = User::factory()->create(['statistieken_toegang' => true]);
        $user->roles()->attach(Role::query()->firstOrCreate(['slug' => 'beheerder'], ['naam' => 'Beheerder']));
        $this->actingAs($user);
        session(['two_factor_verified' => true]);

        return $user;
    }

    public function test_beheerder_kan_mailteksten_en_handtekening_niet_aanpassen(): void
    {
        $this->beheerder();

        $this->putJson('/api/beheer/mail/templates/welkom_gebruiker', [
            'onderwerp' => 'Test',
            'inhoud' => 'Test inhoud',
        ])->assertStatus(403);

        $this->putJson('/api/beheer/mail/signature', ['inhoud' => 'Handtekening'])->assertStatus(403);
        $this->postJson('/api/beheer/mail/preview', [
            'onderwerp' => 'Test',
            'inhoud' => 'Test inhoud',
        ])->assertStatus(403);
    }

    public function test_mail_templates_endpoint_vereist_authenticatie(): void
    {
        $this->getJson('/api/beheer/mail/templates')->assertStatus(401);
    }

    public function test_index_geeft_alle_templates_terug_met_standaardwaarden(): void
    {
        $this->inloggen();

        $response = $this->getJson('/api/beheer/mail/templates');

        $response->assertOk()
            ->assertJsonCount(11, 'data')
            ->assertJsonFragment(['sleutel' => 'welkom_gebruiker', 'aangepast' => false]);
    }

    public function test_show_geeft_404_voor_onbekende_sleutel(): void
    {
        $this->inloggen();

        $this->getJson('/api/beheer/mail/templates/onbekend')->assertStatus(404);
    }

    public function test_show_geeft_specifieke_template_terug(): void
    {
        $this->inloggen();

        $response = $this->getJson('/api/beheer/mail/templates/welkom_gebruiker');

        $response->assertOk()
            ->assertJsonPath('data.sleutel', 'welkom_gebruiker')
            ->assertJsonPath('data.onderwerp.nl', 'Welkom bij {{app_name}}')
            ->assertJsonPath('data.onderwerp.en', 'Welcome to {{app_name}}');
    }

    public function test_update_valideert_verplichte_velden(): void
    {
        $this->inloggen();

        $this->putJson('/api/beheer/mail/templates/welkom_gebruiker', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['onderwerp', 'inhoud']);
    }

    public function test_update_slaat_aangepaste_tekst_op_en_markeert_als_aangepast(): void
    {
        $this->inloggen();

        $response = $this->putJson('/api/beheer/mail/templates/welkom_gebruiker', [
            'onderwerp' => ['nl' => 'Hallo en welkom!', 'en' => 'Hello and welcome!'],
            'inhoud' => [
                'nl' => 'Beste {{voornaam}}, fijn dat je er bent.',
                'en' => 'Dear {{voornaam}}, glad you are here.',
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.onderwerp.nl', 'Hallo en welkom!')
            ->assertJsonPath('data.onderwerp.en', 'Hello and welcome!')
            ->assertJsonPath('data.inhoud.nl', 'Beste {{voornaam}}, fijn dat je er bent.')
            ->assertJsonPath('data.aangepast', true);

        $this->assertSame('Hallo en welkom!', Option::getValue('mail_template.welkom_gebruiker.onderwerp'));
        $this->assertSame('Hello and welcome!', Option::getValue('mail_template.welkom_gebruiker.onderwerp_en'));
    }

    public function test_update_geeft_404_voor_onbekende_sleutel(): void
    {
        $this->inloggen();

        $this->putJson('/api/beheer/mail/templates/onbekend', [
            'onderwerp' => ['nl' => 'x', 'en' => 'x'],
            'inhoud' => ['nl' => 'y', 'en' => 'y'],
        ])->assertStatus(404);
    }

    public function test_reset_herstelt_standaardtekst(): void
    {
        $this->inloggen();

        $this->putJson('/api/beheer/mail/templates/welkom_gebruiker', [
            'onderwerp' => ['nl' => 'Aangepast onderwerp', 'en' => 'Custom subject'],
            'inhoud' => ['nl' => 'Aangepaste inhoud', 'en' => 'Custom body'],
        ])->assertOk();

        $response = $this->postJson('/api/beheer/mail/templates/welkom_gebruiker/reset');

        $response->assertOk()
            ->assertJsonPath('data.onderwerp.nl', 'Welkom bij {{app_name}}')
            ->assertJsonPath('data.onderwerp.en', 'Welcome to {{app_name}}')
            ->assertJsonPath('data.aangepast', false);
    }

    public function test_aangepaste_template_wordt_gebruikt_in_verzonden_mail(): void
    {
        Mail::fake();

        $this->inloggen();

        $this->putJson('/api/beheer/mail/templates/welkom_gebruiker', [
            'onderwerp' => [
                'nl' => 'Speciaal welkom voor {{voornaam}}',
                'en' => 'Special welcome for {{voornaam}}',
            ],
            'inhoud' => [
                'nl' => 'Hallo {{voornaam}}, dit is een aangepaste welkomsttekst.',
                'en' => 'Hello {{voornaam}}, this is a custom welcome text.',
            ],
        ])->assertOk();

        $nieuweGebruiker = User::factory()->create(['voornaam' => 'Jan', 'taal' => 'nl']);

        Mail::to($nieuweGebruiker)->send(new WelkomGebruikerMail(
            $nieuweGebruiker,
            'https://example.test/wachtwoord-aanmaken/test-token'
        ));

        Mail::assertSent(WelkomGebruikerMail::class, function (WelkomGebruikerMail $mail) {
            $mail->assertHasSubject('Speciaal welkom voor Jan');
            $rendered = $mail->render();

            return str_contains($rendered, 'Hallo Jan, dit is een aangepaste welkomsttekst.');
        });
    }

    public function test_engelse_accounttaal_kiest_engelse_mailtekst(): void
    {
        Mail::fake();

        $this->inloggen();

        $gebruiker = User::factory()->create(['voornaam' => 'Jane', 'taal' => 'en']);

        Mail::to($gebruiker)->send(new WelkomGebruikerMail(
            $gebruiker,
            'https://example.test/wachtwoord-aanmaken/test-token'
        ));

        Mail::assertSent(WelkomGebruikerMail::class, function (WelkomGebruikerMail $mail) {
            $mail->assertHasSubject('Welcome to Preekrooster');
            $rendered = $mail->render();

            return str_contains($rendered, 'Dear Jane, your account has been created successfully.');
        });
    }

    public function test_signature_endpoint_vereist_authenticatie(): void
    {
        $this->getJson('/api/beheer/mail/signature')->assertStatus(401);
    }

    public function test_signature_geeft_lege_standaardwaarde_terug(): void
    {
        $this->inloggen();

        $this->getJson('/api/beheer/mail/signature')
            ->assertOk()
            ->assertJsonPath('data.inhoud', '')
            ->assertJsonPath('data.aangepast', false);
    }

    public function test_signature_kan_worden_opgeslagen(): void
    {
        $this->inloggen();

        $response = $this->putJson('/api/beheer/mail/signature', [
            'inhoud' => "Met vriendelijke groet,\nHet {{app_name}}-team",
        ]);

        $response->assertOk()
            ->assertJsonPath('data.inhoud', "Met vriendelijke groet,\nHet {{app_name}}-team")
            ->assertJsonPath('data.aangepast', true);

        $this->assertSame(
            "Met vriendelijke groet,\nHet {{app_name}}-team",
            Option::getValue('mail_signature.inhoud')
        );
    }

    public function test_signature_update_valideert_invoer(): void
    {
        $this->inloggen();

        $this->putJson('/api/beheer/mail/signature', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['inhoud']);
    }

    public function test_signature_kan_worden_gereset(): void
    {
        $this->inloggen();

        $this->putJson('/api/beheer/mail/signature', ['inhoud' => 'Groetjes'])->assertOk();

        $response = $this->postJson('/api/beheer/mail/signature/reset');

        $response->assertOk()
            ->assertJsonPath('data.inhoud', '')
            ->assertJsonPath('data.aangepast', false);
    }

    public function test_handtekening_verschijnt_onderaan_verzonden_mail(): void
    {
        Mail::fake();

        $this->inloggen();

        $this->putJson('/api/beheer/mail/signature', [
            'inhoud' => "Met vriendelijke groet,\n{{app_name}} team",
        ])->assertOk();

        $nieuweGebruiker = User::factory()->create(['voornaam' => 'Jan']);

        Mail::to($nieuweGebruiker)->send(new WelkomGebruikerMail(
            $nieuweGebruiker,
            'https://example.test/wachtwoord-aanmaken/test-token'
        ));

        Mail::assertSent(WelkomGebruikerMail::class, function (WelkomGebruikerMail $mail) {
            $rendered = $mail->render();

            return str_contains($rendered, 'Met vriendelijke groet,')
                && str_contains($rendered, 'team');
        });
    }

    public function test_preview_endpoint_vereist_authenticatie(): void
    {
        $this->postJson('/api/beheer/mail/preview', [
            'onderwerp' => 'x',
            'inhoud' => 'y',
        ])->assertStatus(401);
    }

    public function test_preview_vult_placeholders_in_en_toont_opgeslagen_handtekening(): void
    {
        $this->inloggen();

        $this->putJson('/api/beheer/mail/signature', ['inhoud' => 'Met vriendelijke groet, het team'])->assertOk();

        $response = $this->postJson('/api/beheer/mail/preview', [
            'sleutel' => 'welkom_gebruiker',
            'onderwerp' => 'Welkom {{voornaam}}',
            'inhoud' => 'Beste {{voornaam}}, welkom bij {{app_name}}.',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.onderwerp', 'Welkom Jan');

        $html = $response->json('data.html');
        $text = $response->json('data.text');

        $this->assertStringContainsString('Beste Jan, welkom bij Preekrooster.', $html);
        $this->assertStringContainsString('Met vriendelijke groet, het team', $html);
        $this->assertStringContainsString('Beste Jan, welkom bij Preekrooster.', $text);
        $this->assertStringContainsString('Met vriendelijke groet, het team', $text);
    }

    public function test_preview_kan_conceptondertekening_overschrijven_zonder_op_te_slaan(): void
    {
        $this->inloggen();

        $this->putJson('/api/beheer/mail/signature', ['inhoud' => 'Opgeslagen handtekening'])->assertOk();

        $response = $this->postJson('/api/beheer/mail/preview', [
            'onderwerp' => 'Voorbeeld',
            'inhoud' => 'Voorbeeldtekst.',
            'handtekening' => 'Conceptondertekening (nog niet opgeslagen)',
        ]);

        $response->assertOk();
        $html = $response->json('data.html');

        $this->assertStringContainsString('Conceptondertekening (nog niet opgeslagen)', $html);
        $this->assertStringNotContainsString('Opgeslagen handtekening', $html);

        $this->assertSame('Opgeslagen handtekening', Option::getValue('mail_signature.inhoud'));
    }

    public function test_preview_valideert_verplichte_velden(): void
    {
        $this->inloggen();

        $this->postJson('/api/beheer/mail/preview', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['onderwerp', 'inhoud']);
    }

    public function test_preview_welkom_gebruiker_toont_wachtwoordknop_en_inhoud(): void
    {
        $this->inloggen();

        $response = $this->postJson('/api/beheer/mail/preview', [
            'sleutel' => 'welkom_gebruiker',
            'onderwerp' => 'Welkom {{voornaam}}',
            'inhoud' => 'Beste {{voornaam}}, welkom bij {{app_name}}.',
        ]);

        $response->assertOk();
        $html = $response->json('data.html');
        $text = $response->json('data.text');

        $this->assertStringContainsString('Beste Jan, welkom bij Preekrooster.', $html);
        $this->assertStringContainsString('Wachtwoord aanmaken', $html);
        $this->assertStringContainsString('Beste Jan, welkom bij Preekrooster.', $text);
        $this->assertStringContainsString('Stel uw wachtwoord in via:', $text);
    }

    public function test_preview_predikant_uitvraag_toont_dienstinfo_en_knop(): void
    {
        $this->inloggen();

        $response = $this->postJson('/api/beheer/mail/preview', [
            'sleutel' => 'predikant_uitvraag',
            'onderwerp' => 'Uitvraag',
            'inhoud' => 'Beste {{voornaam}}, u bent uitgevraagd.',
        ]);

        $response->assertOk();
        $html = $response->json('data.html');

        $this->assertStringContainsString('Beste Jan, u bent uitgevraagd.', $html);
        $this->assertStringContainsString('Wijhe', $html);
        $this->assertStringContainsString('Bevestig of wijs af', $html);
    }

    public function test_preview_zonder_sleutel_blijft_generiek(): void
    {
        $this->inloggen();

        $response = $this->postJson('/api/beheer/mail/preview', [
            'onderwerp' => 'Handtekeningvoorbeeld',
            'inhoud' => 'Alleen tekst zonder knoppen.',
        ]);

        $response->assertOk();
        $html = $response->json('data.html');

        $this->assertStringContainsString('Handtekeningvoorbeeld', $html);
        $this->assertStringContainsString('Alleen tekst zonder knoppen.', $html);
        $this->assertStringNotContainsString('Wachtwoord aanmaken', $html);
        $this->assertStringNotContainsString('Bevestig of wijs af', $html);
    }
}

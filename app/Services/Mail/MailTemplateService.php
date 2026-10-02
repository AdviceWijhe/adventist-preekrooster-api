<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Models\Option;
use Illuminate\Support\Arr;

class MailTemplateService
{
    private const OPTION_PREFIX = 'mail_template.';

    private const SIGNATURE_OPTION_KEY = 'mail_signature.inhoud';

    private const SIGNATURE_DEFAULT = '';

    /**
     * Registry van alle bewerkbare mailteksten. `variabelen` is puur informatief
     * voor de beheerinterface (welke {{placeholders}} beschikbaar zijn); de
     * daadwerkelijke waarden worden per verzending door de Mailable aangeleverd.
     *
     * @var array<string, array{label: string, variabelen: array<string, string>, onderwerp: string, inhoud: string}>
     */
    private const DEFAULTS = [
        'welkom_gebruiker' => [
            'label' => 'Welkom nieuwe gebruiker',
            'variabelen' => ['voornaam' => 'Voornaam van de gebruiker', 'app_name' => 'Naam van de applicatie'],
            'onderwerp' => 'Welkom bij {{app_name}}',
            'inhoud' => 'Beste {{voornaam}}, uw account is succesvol aangemaakt. Stel binnen 7 dagen uw wachtwoord in via de knop hieronder.',
        ],
        'account_geactiveerd' => [
            'label' => 'Account geactiveerd',
            'variabelen' => ['voornaam' => 'Voornaam van de gebruiker'],
            'onderwerp' => 'Uw account is geactiveerd',
            'inhoud' => 'Beste {{voornaam}}, uw account is weer actief.',
        ],
        'account_inactiviteit_waarschuwing' => [
            'label' => 'Waarschuwing account-inactiviteit',
            'variabelen' => [
                'voornaam' => 'Voornaam van de gebruiker',
                'app_name' => 'Naam van de applicatie',
                'dagen' => 'Aantal dagen tot deactivatie',
            ],
            'onderwerp' => 'Herinnering: account wordt binnenkort gedeactiveerd',
            'inhoud' => 'Beste {{voornaam}}, je account in {{app_name}} is al langere tijd niet gebruikt. Als je niet binnen {{dagen}} dag(en) inlogt, wordt je account op niet-actief gezet. Je kunt dan niet meer inloggen totdat een beheerder je account weer activeert.',
        ],
        'profiel_gewijzigd' => [
            'label' => 'Profiel gewijzigd',
            'variabelen' => ['voornaam' => 'Voornaam van de gebruiker'],
            'onderwerp' => 'Uw profiel is gewijzigd',
            'inhoud' => 'Beste {{voornaam}}, er zijn wijzigingen doorgevoerd in uw profiel.',
        ],
        'predikant_uitvraag' => [
            'label' => 'Uitvraag predikant',
            'variabelen' => ['voornaam' => 'Voornaam van de predikant'],
            'onderwerp' => 'U bent uitgevraagd voor een dienst',
            'inhoud' => 'Beste {{voornaam}}, u bent uitgevraagd voor onderstaande dienst:',
        ],
        'beurt_status_melding' => [
            'label' => 'Update preekbeurt status',
            'variabelen' => [],
            'onderwerp' => 'Update preekbeurt status',
            'inhoud' => 'Een predikant heeft gereageerd op een uitvraag.',
        ],
        'beurt_annulering' => [
            'label' => 'Preekbeurt geannuleerd',
            'variabelen' => ['spreker' => 'Naam van de predikant', 'gemeente' => 'Naam van de gemeente'],
            'onderwerp' => 'Preekbeurt geannuleerd — {{gemeente}}',
            'inhoud' => '{{spreker}} heeft een bevestigde preekbeurt geannuleerd:',
        ],
        'spreekbeurt_auto_annulering' => [
            'label' => 'Preekbeurt automatisch geannuleerd',
            'variabelen' => [],
            'onderwerp' => 'Preekbeurt automatisch geannuleerd (niet bevestigd)',
            'inhoud' => 'De volgende preekbeurt is verwijderd omdat deze niet tijdig is bevestigd:',
        ],
        'bevestigings_herinnering' => [
            'label' => 'Herinnering bevestigen',
            'variabelen' => ['voornaam' => 'Voornaam van de predikant'],
            'onderwerp' => 'Herinnering: bevestig uw preekbeurten',
            'inhoud' => 'Beste {{voornaam}}, hieronder staan de beurten die nog om een reactie vragen.',
        ],
        'publicatie_bekendmaking' => [
            'label' => 'Rooster gepubliceerd (bekendmaking)',
            'variabelen' => ['periode' => 'Periode, bv. 2026-07'],
            'onderwerp' => 'Rooster {{periode}} gepubliceerd',
            'inhoud' => 'Het rooster voor periode {{periode}} is gepubliceerd.',
        ],
        'rooster_publicatie' => [
            'label' => 'Preekrooster (volledige mail)',
            'variabelen' => ['maand' => 'Maand en jaar, bv. juli 2026', 'gemeente' => 'Naam van de gemeente'],
            'onderwerp' => 'Preekrooster {{maand}}',
            'inhoud' => 'Het rooster voor {{gemeente}} is beschikbaar.',
        ],
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function alle(): array
    {
        return array_values(array_map(
            fn (string $sleutel, array $definitie): array => $this->metOverrides($sleutel, $definitie),
            array_keys(self::DEFAULTS),
            self::DEFAULTS,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $sleutel): array
    {
        $definitie = self::DEFAULTS[$sleutel] ?? null;
        abort_if($definitie === null, 404, 'Onbekende mailtemplate.');

        return $this->metOverrides($sleutel, $definitie);
    }

    public function bestaat(string $sleutel): bool
    {
        return array_key_exists($sleutel, self::DEFAULTS);
    }

    /**
     * @return array<string, mixed>
     */
    public function update(string $sleutel, string $onderwerp, string $inhoud): array
    {
        abort_if(! $this->bestaat($sleutel), 404, 'Onbekende mailtemplate.');

        Option::setValue(self::OPTION_PREFIX.$sleutel.'.onderwerp', trim($onderwerp));
        Option::setValue(self::OPTION_PREFIX.$sleutel.'.inhoud', trim($inhoud));

        return $this->get($sleutel);
    }

    /**
     * @return array<string, mixed>
     */
    public function resetten(string $sleutel): array
    {
        abort_if(! $this->bestaat($sleutel), 404, 'Onbekende mailtemplate.');

        Option::query()->where('naam', self::OPTION_PREFIX.$sleutel.'.onderwerp')->delete();
        Option::query()->where('naam', self::OPTION_PREFIX.$sleutel.'.inhoud')->delete();

        return $this->get($sleutel);
    }

    /**
     * Rendert onderwerp + inhoud van een template met de opgegeven variabelen
     * ingevuld. Onbekende `{{placeholders}}` blijven ongewijzigd staan.
     *
     * @param  array<string, string|null>  $variabelen
     * @return array{onderwerp: string, inhoud: string}
     */
    public function render(string $sleutel, array $variabelen = []): array
    {
        $template = $this->get($sleutel);

        return [
            'onderwerp' => $this->interpoleer((string) $template['onderwerp'], $variabelen),
            'inhoud' => $this->interpoleer((string) $template['inhoud'], $variabelen),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function metOverrides(string $sleutel, array $definitie): array
    {
        $onderwerp = Option::getValue(self::OPTION_PREFIX.$sleutel.'.onderwerp');
        $inhoud = Option::getValue(self::OPTION_PREFIX.$sleutel.'.inhoud');

        $huidigOnderwerp = $onderwerp !== null && $onderwerp !== '' ? $onderwerp : $definitie['onderwerp'];
        $huidigInhoud = $inhoud !== null && $inhoud !== '' ? $inhoud : $definitie['inhoud'];

        return [
            'sleutel' => $sleutel,
            'label' => $definitie['label'],
            'variabelen' => $definitie['variabelen'],
            'onderwerp' => $huidigOnderwerp,
            'inhoud' => $huidigInhoud,
            'standaard_onderwerp' => $definitie['onderwerp'],
            'standaard_inhoud' => $definitie['inhoud'],
            'aangepast' => $huidigOnderwerp !== $definitie['onderwerp'] || $huidigInhoud !== $definitie['inhoud'],
        ];
    }

    /**
     * @return array{inhoud: string, aangepast: bool}
     */
    public function signature(): array
    {
        $inhoud = Option::getValue(self::SIGNATURE_OPTION_KEY);
        $huidig = $inhoud !== null && $inhoud !== '' ? $inhoud : self::SIGNATURE_DEFAULT;

        return [
            'inhoud' => $huidig,
            'aangepast' => $huidig !== self::SIGNATURE_DEFAULT,
        ];
    }

    /**
     * @return array{inhoud: string, aangepast: bool}
     */
    public function updateSignature(string $inhoud): array
    {
        Option::setValue(self::SIGNATURE_OPTION_KEY, trim($inhoud));

        return $this->signature();
    }

    /**
     * @return array{inhoud: string, aangepast: bool}
     */
    public function resetSignature(): array
    {
        Option::query()->where('naam', self::SIGNATURE_OPTION_KEY)->delete();

        return $this->signature();
    }

    /**
     * Rendert de handtekening met de opgegeven variabelen ingevuld (bv. `app_name`).
     * Geeft een lege string terug wanneer er geen handtekening is ingesteld.
     *
     * @param  array<string, string|null>  $variabelen
     */
    public function renderSignature(array $variabelen = []): string
    {
        $inhoud = $this->signature()['inhoud'];

        return $inhoud === '' ? '' : $this->interpoleer($inhoud, $variabelen);
    }

    /**
     * Rendert vrije tekst (bv. een concept-onderwerp/inhoud/handtekening uit de
     * beheerinterface) met de opgegeven variabelen ingevuld. Publieke variant van
     * {@see interpoleer()} t.b.v. de voorbeeldweergave.
     *
     * @param  array<string, string|null>  $variabelen
     */
    public function interpoleerVoorbeeld(string $tekst, array $variabelen): string
    {
        return $this->interpoleer($tekst, $variabelen);
    }

    /**
     * @param  array<string, string|null>  $variabelen
     */
    private function interpoleer(string $tekst, array $variabelen): string
    {
        return (string) preg_replace_callback(
            '/\{\{\s*([a-z_]+)\s*\}\}/i',
            function (array $match) use ($variabelen): string {
                $waarde = Arr::get($variabelen, $match[1]);

                return $waarde !== null ? (string) $waarde : $match[0];
            },
            $tekst,
        );
    }
}

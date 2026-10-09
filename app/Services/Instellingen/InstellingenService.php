<?php

declare(strict_types=1);

namespace App\Services\Instellingen;

use App\Models\Option;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class InstellingenService
{
    public const KEY_BEURT_ANNULEREN_ENABLED = 'instellingen_beurt_annuleren_enabled';

    public const KEY_BEURT_ANNULEREN_DAGEN = 'instellingen_beurt_annuleren_dagen_vooraf';

    public const KEY_INACTIVITEIT_ENABLED = 'instellingen_inactiviteit_enabled';

    public const KEY_INACTIVITEIT_DAGEN = 'instellingen_inactiviteit_dagen';

    public const KEY_INACTIVITEIT_WAARSCHUWING_DAGEN = 'instellingen_inactiviteit_waarschuwing_dagen';

    public const KEY_CHANGELOG_BEWAARTERMIJN_DAGEN = 'instellingen_changelog_bewaartermijn_dagen';

    public const KEY_AVG_ENABLED = 'instellingen_avg_enabled';

    public const KEY_AVG_DAGEN = 'instellingen_avg_dagen';

    public const KEY_AVG_TITEL = 'instellingen_avg_titel';

    public const KEY_AVG_TITEL_EN = 'instellingen_avg_titel_en';

    public const KEY_AVG_TEKST_AKKOORD = 'instellingen_avg_tekst_akkoord';

    public const KEY_AVG_TEKST_AKKOORD_EN = 'instellingen_avg_tekst_akkoord_en';

    public const KEY_AVG_TEKST_WEIGERING = 'instellingen_avg_tekst_weigering';

    public const KEY_AVG_TEKST_WEIGERING_EN = 'instellingen_avg_tekst_weigering_en';

    public const DEFAULT_AVG_TEKST_AKKOORD = 'Door akkoord te gaan, maakt u uw e-mailadres en telefoonnummer inzichtelijk voor alle ingelogde gebruikers.';

    public const DEFAULT_AVG_TEKST_AKKOORD_EN = 'By agreeing, you make your email address and phone number visible to all logged-in users.';

    public const DEFAULT_AVG_TEKST_WEIGERING = 'Als u niet akkoord gaat, wordt uw account binnen {dagen} dagen op inactief gezet.';

    public const DEFAULT_AVG_TEKST_WEIGERING_EN = 'If you do not agree, your account will be deactivated within {dagen} days.';

    public const DEFAULT_AVG_TITEL = 'Privacytoestemming';

    public const DEFAULT_AVG_TITEL_EN = 'Privacy consent';

    /**
     * @var array<string, string>
     */
    private const DEFAULTS = [
        self::KEY_BEURT_ANNULEREN_ENABLED => '1',
        self::KEY_BEURT_ANNULEREN_DAGEN => '7',
        self::KEY_INACTIVITEIT_ENABLED => '0',
        self::KEY_INACTIVITEIT_DAGEN => '365',
        self::KEY_INACTIVITEIT_WAARSCHUWING_DAGEN => '14',
        self::KEY_CHANGELOG_BEWAARTERMIJN_DAGEN => '180',
        self::KEY_AVG_ENABLED => '0',
        self::KEY_AVG_DAGEN => '14',
        self::KEY_AVG_TITEL => self::DEFAULT_AVG_TITEL,
        self::KEY_AVG_TITEL_EN => self::DEFAULT_AVG_TITEL_EN,
        self::KEY_AVG_TEKST_AKKOORD => self::DEFAULT_AVG_TEKST_AKKOORD,
        self::KEY_AVG_TEKST_AKKOORD_EN => self::DEFAULT_AVG_TEKST_AKKOORD_EN,
        self::KEY_AVG_TEKST_WEIGERING => self::DEFAULT_AVG_TEKST_WEIGERING,
        self::KEY_AVG_TEKST_WEIGERING_EN => self::DEFAULT_AVG_TEKST_WEIGERING_EN,
    ];

    /**
     * @return array{
     *     beurt_annuleren: array{enabled: bool, dagen_vooraf: int},
     *     inactiviteit: array{enabled: bool, dagen: int, waarschuwing_dagen: int},
     *     changelog: array{bewaartermijn_dagen: int},
     *     avg: array{
     *         enabled: bool,
     *         dagen: int,
     *         titel: array{nl: string, en: string},
     *         tekst_akkoord: array{nl: string, en: string},
     *         tekst_weigering: array{nl: string, en: string}
     *     }
     * }
     */
    public function all(): array
    {
        return [
            'beurt_annuleren' => $this->beurtAnnuleren(),
            'inactiviteit' => $this->inactiviteit(),
            'changelog' => $this->changelog(),
            'avg' => $this->avg(),
        ];
    }

    /**
     * @return array{bewaartermijn_dagen: int}
     */
    public function changelog(): array
    {
        return [
            'bewaartermijn_dagen' => $this->intValue(self::KEY_CHANGELOG_BEWAARTERMIJN_DAGEN, 180),
        ];
    }

    /**
     * @return array{enabled: bool, dagen_vooraf: int}
     */
    public function beurtAnnuleren(): array
    {
        return [
            'enabled' => $this->boolValue(self::KEY_BEURT_ANNULEREN_ENABLED),
            'dagen_vooraf' => $this->intValue(self::KEY_BEURT_ANNULEREN_DAGEN, 7),
        ];
    }

    /**
     * @return array{enabled: bool, dagen: int, waarschuwing_dagen: int}
     */
    public function inactiviteit(): array
    {
        return [
            'enabled' => $this->boolValue(self::KEY_INACTIVITEIT_ENABLED),
            'dagen' => $this->intValue(self::KEY_INACTIVITEIT_DAGEN, 365),
            'waarschuwing_dagen' => $this->intValue(self::KEY_INACTIVITEIT_WAARSCHUWING_DAGEN, 14),
        ];
    }

    /**
     * @return array{
     *     enabled: bool,
     *     dagen: int,
     *     titel: array{nl: string, en: string},
     *     tekst_akkoord: array{nl: string, en: string},
     *     tekst_weigering: array{nl: string, en: string}
     * }
     */
    public function avg(): array
    {
        return [
            'enabled' => $this->boolValue(self::KEY_AVG_ENABLED),
            'dagen' => $this->intValue(self::KEY_AVG_DAGEN, 14),
            'titel' => [
                'nl' => $this->stringValue(self::KEY_AVG_TITEL, self::DEFAULT_AVG_TITEL),
                'en' => $this->stringValue(self::KEY_AVG_TITEL_EN, self::DEFAULT_AVG_TITEL_EN),
            ],
            'tekst_akkoord' => [
                'nl' => $this->stringValue(self::KEY_AVG_TEKST_AKKOORD, self::DEFAULT_AVG_TEKST_AKKOORD),
                'en' => $this->stringValue(self::KEY_AVG_TEKST_AKKOORD_EN, self::DEFAULT_AVG_TEKST_AKKOORD_EN),
            ],
            'tekst_weigering' => [
                'nl' => $this->stringValue(self::KEY_AVG_TEKST_WEIGERING, self::DEFAULT_AVG_TEKST_WEIGERING),
                'en' => $this->stringValue(self::KEY_AVG_TEKST_WEIGERING_EN, self::DEFAULT_AVG_TEKST_WEIGERING_EN),
            ],
        ];
    }

    /**
     * @return array{titel: string, tekst_akkoord: string, tekst_weigering: string, dagen: int, enabled: bool}
     */
    public function avgVoorTaal(string $taal): array
    {
        $avg = $this->avg();
        $locale = $taal === 'en' ? 'en' : 'nl';

        return [
            'enabled' => $avg['enabled'],
            'dagen' => $avg['dagen'],
            'titel' => $avg['titel'][$locale],
            'tekst_akkoord' => $avg['tekst_akkoord'][$locale],
            'tekst_weigering' => $avg['tekst_weigering'][$locale],
        ];
    }

    public function magBeurtAnnulerenOpDatum(Carbon $dienstDatum): bool
    {
        $settings = $this->beurtAnnuleren();
        if (! $settings['enabled']) {
            return false;
        }

        $dagenVooraf = $settings['dagen_vooraf'];
        $uitersteAnnuleerDag = now()->startOfDay()->addDays($dagenVooraf);

        return $dienstDatum->copy()->startOfDay()->gte($uitersteAnnuleerDag);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     beurt_annuleren: array{enabled: bool, dagen_vooraf: int},
     *     inactiviteit: array{enabled: bool, dagen: int, waarschuwing_dagen: int},
     *     changelog: array{bewaartermijn_dagen: int},
     *     avg: array{
     *         enabled: bool,
     *         dagen: int,
     *         titel: array{nl: string, en: string},
     *         tekst_akkoord: array{nl: string, en: string},
     *         tekst_weigering: array{nl: string, en: string}
     *     }
     * }
     */
    public function update(array $input): array
    {
        if (array_key_exists('beurt_annuleren', $input) && is_array($input['beurt_annuleren'])) {
            $this->updateBeurtAnnuleren($input['beurt_annuleren']);
        }

        if (array_key_exists('inactiviteit', $input) && is_array($input['inactiviteit'])) {
            $this->updateInactiviteit($input['inactiviteit']);
        }

        if (array_key_exists('changelog', $input) && is_array($input['changelog'])) {
            $this->updateChangelog($input['changelog']);
        }

        if (array_key_exists('avg', $input) && is_array($input['avg'])) {
            $this->updateAvg($input['avg']);
        }

        return $this->all();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function updateBeurtAnnuleren(array $input): void
    {
        if (array_key_exists('enabled', $input)) {
            Option::setValue(self::KEY_BEURT_ANNULEREN_ENABLED, $input['enabled'] ? '1' : '0');
        }

        if (array_key_exists('dagen_vooraf', $input)) {
            Option::setValue(self::KEY_BEURT_ANNULEREN_DAGEN, (string) max(0, (int) $input['dagen_vooraf']));
        }
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function updateInactiviteit(array $input): void
    {
        if (array_key_exists('enabled', $input)) {
            Option::setValue(self::KEY_INACTIVITEIT_ENABLED, $input['enabled'] ? '1' : '0');
        }

        $dagen = array_key_exists('dagen', $input)
            ? max(30, (int) $input['dagen'])
            : $this->intValue(self::KEY_INACTIVITEIT_DAGEN, 365);

        $waarschuwing = array_key_exists('waarschuwing_dagen', $input)
            ? max(1, (int) $input['waarschuwing_dagen'])
            : $this->intValue(self::KEY_INACTIVITEIT_WAARSCHUWING_DAGEN, 14);

        if ($waarschuwing >= $dagen) {
            $waarschuwing = max(1, $dagen - 1);
        }

        Option::setValue(self::KEY_INACTIVITEIT_DAGEN, (string) $dagen);
        Option::setValue(self::KEY_INACTIVITEIT_WAARSCHUWING_DAGEN, (string) $waarschuwing);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function updateChangelog(array $input): void
    {
        if (array_key_exists('bewaartermijn_dagen', $input)) {
            Option::setValue(self::KEY_CHANGELOG_BEWAARTERMIJN_DAGEN, (string) max(7, (int) $input['bewaartermijn_dagen']));
        }
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function updateAvg(array $input): void
    {
        if (array_key_exists('enabled', $input)) {
            Option::setValue(self::KEY_AVG_ENABLED, $input['enabled'] ? '1' : '0');
        }

        if (array_key_exists('dagen', $input)) {
            Option::setValue(self::KEY_AVG_DAGEN, (string) max(1, min(365, (int) $input['dagen'])));
        }

        $this->updateLocalizedAvgText($input, 'titel', self::KEY_AVG_TITEL, self::KEY_AVG_TITEL_EN, self::DEFAULT_AVG_TITEL, self::DEFAULT_AVG_TITEL_EN);
        $this->updateLocalizedAvgText($input, 'tekst_akkoord', self::KEY_AVG_TEKST_AKKOORD, self::KEY_AVG_TEKST_AKKOORD_EN, self::DEFAULT_AVG_TEKST_AKKOORD, self::DEFAULT_AVG_TEKST_AKKOORD_EN);
        $this->updateLocalizedAvgText($input, 'tekst_weigering', self::KEY_AVG_TEKST_WEIGERING, self::KEY_AVG_TEKST_WEIGERING_EN, self::DEFAULT_AVG_TEKST_WEIGERING, self::DEFAULT_AVG_TEKST_WEIGERING_EN);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function updateLocalizedAvgText(
        array $input,
        string $field,
        string $keyNl,
        string $keyEn,
        string $defaultNl,
        string $defaultEn,
    ): void {
        if (! array_key_exists($field, $input) || ! is_array($input[$field])) {
            return;
        }

        if (array_key_exists('nl', $input[$field])) {
            $tekst = trim((string) $input[$field]['nl']);
            Option::setValue($keyNl, $tekst !== '' ? $tekst : $defaultNl);
        }

        if (array_key_exists('en', $input[$field])) {
            $tekst = trim((string) $input[$field]['en']);
            Option::setValue($keyEn, $tekst !== '' ? $tekst : $defaultEn);
        }
    }

    private function boolValue(string $key): bool
    {
        return $this->rawValue($key) === '1';
    }

    private function intValue(string $key, int $fallback): int
    {
        $raw = $this->rawValue($key);

        return is_numeric($raw) ? (int) $raw : $fallback;
    }

    private function stringValue(string $key, string $fallback): string
    {
        $raw = $this->rawValue($key);

        return $raw !== '' ? $raw : $fallback;
    }

    private function rawValue(string $key): string
    {
        if (! Schema::hasTable('options')) {
            return self::DEFAULTS[$key] ?? '';
        }

        $value = Option::getValue($key);

        return ($value === null || $value === '') ? (self::DEFAULTS[$key] ?? '') : $value;
    }
}

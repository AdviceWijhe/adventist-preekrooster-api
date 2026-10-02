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
    ];

    /**
     * @return array{
     *     beurt_annuleren: array{enabled: bool, dagen_vooraf: int},
     *     inactiviteit: array{enabled: bool, dagen: int, waarschuwing_dagen: int},
     *     changelog: array{bewaartermijn_dagen: int}
     * }
     */
    public function all(): array
    {
        return [
            'beurt_annuleren' => $this->beurtAnnuleren(),
            'inactiviteit' => $this->inactiviteit(),
            'changelog' => $this->changelog(),
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
     *     changelog: array{bewaartermijn_dagen: int}
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

    private function boolValue(string $key): bool
    {
        return $this->rawValue($key) === '1';
    }

    private function intValue(string $key, int $fallback): int
    {
        $raw = $this->rawValue($key);

        return is_numeric($raw) ? (int) $raw : $fallback;
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

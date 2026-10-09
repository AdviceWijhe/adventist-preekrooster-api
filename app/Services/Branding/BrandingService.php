<?php

declare(strict_types=1);

namespace App\Services\Branding;

use App\Models\Option;
use Illuminate\Support\Facades\Schema;

class BrandingService
{
    /**
     * @var array<string, string>
     */
    private const DEFAULTS = [
        'branding_app_name' => 'Preekrooster',
        'branding_logo_url' => '',
        'branding_primary_color' => '#2F557F',
        'branding_secondary_color' => '#04132B',
        'branding_accent_color' => '#E2E8F0',
        'branding_from_name' => 'Preekrooster Adventist Nederland',
        'branding_footer_text' => 'Deze e-mail is automatisch verzonden door Preekrooster.',
    ];

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        $result = [];
        if (! Schema::hasTable('options')) {
            $result = self::DEFAULTS;
            $result['branding_frontend_url'] = rtrim((string) config('app.frontend_url'), '/');

            return $result;
        }

        foreach (self::DEFAULTS as $key => $defaultValue) {
            $value = Option::getValue($key);
            $result[$key] = ($value === null || $value === '') ? $defaultValue : $value;
        }

        $result['branding_frontend_url'] = rtrim((string) config('app.frontend_url'), '/');

        return $result;
    }

    /**
     * @return array<string, string>
     */
    public function mailBranding(): array
    {
        $all = $this->all();

        return [
            'app_name' => $all['branding_app_name'],
            'logo_url' => $all['branding_logo_url'],
            'primary_color' => $this->sanitizeHex($all['branding_primary_color'], self::DEFAULTS['branding_primary_color']),
            'secondary_color' => $this->sanitizeHex($all['branding_secondary_color'], self::DEFAULTS['branding_secondary_color']),
            'accent_color' => $this->sanitizeHex($all['branding_accent_color'], self::DEFAULTS['branding_accent_color']),
            'from_name' => $all['branding_from_name'],
            'footer_text' => $all['branding_footer_text'],
            'frontend_url' => $all['branding_frontend_url'],
        ];
    }

    /**
     * @param  array<string, string|null>  $input
     */
    public function update(array $input): array
    {
        foreach (self::DEFAULTS as $key => $defaultValue) {
            if (array_key_exists($key, $input) && $input[$key] !== null) {
                Option::setValue($key, trim((string) $input[$key]));
            }
        }

        return $this->all();
    }

    private function sanitizeHex(string $value, string $fallback): string
    {
        return preg_match('/^#[0-9A-Fa-f]{6}$/', $value) === 1 ? strtoupper($value) : $fallback;
    }
}

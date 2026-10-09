<?php

declare(strict_types=1);

namespace App\Mail\Concerns;

use App\Models\User;
use App\Services\Mail\MailTemplateService;

trait InteractsWithMailTemplate
{
    /**
     * Rendert de door de beheerder aangepaste (of standaard) tekst voor een
     * mailtemplate, met de opgegeven variabelen ingevuld. `app_name` wordt
     * automatisch meegegeven vanuit de branding.
     *
     * Taal volgt optioneel `$taal`, anders de accounttaal van `user` /
     * `predikant` op de mailable, anders Nederlands.
     *
     * @param  array<string, string|null>  $variabelen
     * @return array{onderwerp: string, inhoud: string}
     */
    protected function mailTemplate(string $sleutel, array $variabelen = [], ?string $taal = null): array
    {
        $appName = method_exists($this, 'branding') ? ($this->branding()['app_name'] ?? null) : null;

        return app(MailTemplateService::class)->render(
            $sleutel,
            [
                'app_name' => $appName ?? config('app.name'),
                ...$variabelen,
            ],
            $this->resolveMailTaal($taal),
        );
    }

    protected function resolveMailTaal(?string $taal = null): string
    {
        if ($taal === 'en' || $taal === 'nl') {
            return $taal;
        }

        foreach (['user', 'predikant', 'contactpersoon', 'ontvanger'] as $property) {
            if (! property_exists($this, $property)) {
                continue;
            }

            $candidate = $this->{$property};
            if ($candidate instanceof User) {
                return $candidate->taal === 'en' ? 'en' : 'nl';
            }
        }

        return 'nl';
    }
}

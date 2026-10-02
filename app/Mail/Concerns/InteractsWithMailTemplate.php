<?php

declare(strict_types=1);

namespace App\Mail\Concerns;

use App\Services\Mail\MailTemplateService;

trait InteractsWithMailTemplate
{
    /**
     * Rendert de door de beheerder aangepaste (of standaard) tekst voor een
     * mailtemplate, met de opgegeven variabelen ingevuld. `app_name` wordt
     * automatisch meegegeven vanuit de branding.
     *
     * @param  array<string, string|null>  $variabelen
     * @return array{onderwerp: string, inhoud: string}
     */
    protected function mailTemplate(string $sleutel, array $variabelen = []): array
    {
        $appName = method_exists($this, 'branding') ? ($this->branding()['app_name'] ?? null) : null;

        return app(MailTemplateService::class)->render($sleutel, [
            'app_name' => $appName ?? config('app.name'),
            ...$variabelen,
        ]);
    }
}

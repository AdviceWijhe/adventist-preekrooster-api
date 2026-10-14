<?php

declare(strict_types=1);

namespace App\Mail\Concerns;

use App\Services\Branding\BrandingService;

trait InteractsWithBranding
{
    /**
     * @return array<string, string>
     */
    protected function branding(): array
    {
        return app(BrandingService::class)->mailBranding();
    }
}

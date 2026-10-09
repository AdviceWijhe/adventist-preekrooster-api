<?php

declare(strict_types=1);

namespace App\Services\Security;

use RuntimeException;

class ProductionConfigGuard
{
    /**
     * Weigert te booten op production/staging wanneer kritieke security-config
     * is uitgeschakeld (2FA, secure session cookie, session encryptie).
     */
    public function assertSafe(): void
    {
        if (! app()->environment(['production', 'staging'])) {
            return;
        }

        if (! config('two_factor.enabled')) {
            throw new RuntimeException(
                'TWO_FACTOR_ENABLED must be true in production/staging. Do not disable 2FA outside local development.'
            );
        }

        if (! config('session.secure')) {
            throw new RuntimeException(
                'SESSION_SECURE_COOKIE must be true in production/staging so session cookies are HTTPS-only.'
            );
        }

        if (! config('session.encrypt')) {
            throw new RuntimeException(
                'SESSION_ENCRYPT must be true in production/staging so session payloads are encrypted at rest.'
            );
        }
    }
}

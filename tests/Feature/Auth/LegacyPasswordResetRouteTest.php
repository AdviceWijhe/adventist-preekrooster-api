<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Tests\TestCase;

class LegacyPasswordResetRouteTest extends TestCase
{
    public function test_legacy_reset_password_route_geeft_404(): void
    {
        $this->get('/reset-password/voorbeeld-token')->assertNotFound();
    }
}

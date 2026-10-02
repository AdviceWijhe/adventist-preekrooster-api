<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Security;

use App\Services\Security\ProductionConfigGuard;
use RuntimeException;
use Tests\TestCase;

class ProductionConfigGuardTest extends TestCase
{
    public function test_productie_met_2fa_uit_gooit_exception(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        config([
            'two_factor.enabled' => false,
            'session.secure' => true,
            'session.encrypt' => true,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('TWO_FACTOR_ENABLED');

        (new ProductionConfigGuard)->assertSafe();
    }

    public function test_testing_met_2fa_uit_gooit_geen_exception(): void
    {
        $this->app->detectEnvironment(fn (): string => 'testing');
        config([
            'two_factor.enabled' => false,
            'session.secure' => false,
            'session.encrypt' => false,
        ]);

        (new ProductionConfigGuard)->assertSafe();

        $this->assertTrue(true);
    }

    public function test_staging_met_secure_cookie_uit_gooit_exception(): void
    {
        $this->app->detectEnvironment(fn (): string => 'staging');
        config([
            'two_factor.enabled' => true,
            'session.secure' => false,
            'session.encrypt' => true,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SESSION_SECURE_COOKIE');

        (new ProductionConfigGuard)->assertSafe();
    }

    public function test_productie_met_session_encrypt_uit_gooit_exception(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        config([
            'two_factor.enabled' => true,
            'session.secure' => true,
            'session.encrypt' => false,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SESSION_ENCRYPT');

        (new ProductionConfigGuard)->assertSafe();
    }

    public function test_productie_met_veilige_config_slaagt(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        config([
            'two_factor.enabled' => true,
            'session.secure' => true,
            'session.encrypt' => true,
        ]);

        (new ProductionConfigGuard)->assertSafe();

        $this->assertTrue(true);
    }
}

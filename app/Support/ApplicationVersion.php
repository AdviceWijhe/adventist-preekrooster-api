<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Canonical application version shown in Beheer → Instellingen.
 * Bump NUMMER manually when releasing; agents should propose a bump for notable user-facing changes.
 */
final class ApplicationVersion
{
    public const NUMMER = '4.1';

    public static function nummer(): string
    {
        return self::NUMMER;
    }
}

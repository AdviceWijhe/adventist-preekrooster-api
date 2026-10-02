<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    private const SUPPORTED = ['nl', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale($this->resolveLocale($request));

        return $next($request);
    }

    private function resolveLocale(Request $request): string
    {
        $header = strtolower(trim((string) $request->header('X-Locale', '')));
        if (in_array($header, self::SUPPORTED, true)) {
            return $header;
        }

        $user = $request->user();
        if ($user !== null && in_array($user->taal ?? '', self::SUPPORTED, true)) {
            return $user->taal;
        }

        $configured = config('app.locale', 'nl');
        if (in_array($configured, self::SUPPORTED, true)) {
            return $configured;
        }

        $acceptLanguage = $request->header('Accept-Language');
        if (is_string($acceptLanguage) && $acceptLanguage !== '') {
            $primary = strtolower(substr(trim(explode(',', $acceptLanguage)[0]), 0, 2));
            if (in_array($primary, self::SUPPORTED, true)) {
                return $primary;
            }
        }

        return 'nl';
    }
}

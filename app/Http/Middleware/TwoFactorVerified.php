<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('two_factor.enabled')) {
            return $next($request);
        }

        if (! $request->session()->get('two_factor_verified', false)) {
            return new JsonResponse(['message' => __('api.middleware.two_factor_required')], 403);
        }

        return $next($request);
    }
}

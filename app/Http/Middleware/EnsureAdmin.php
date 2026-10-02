<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return new JsonResponse(['message' => __('api.middleware.not_logged_in')], 401);
        }

        if (! $user->isAdmin()) {
            return new JsonResponse(['message' => __('api.middleware.insufficient_permissions')], 403);
        }

        return $next($request);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Avg\AvgConsentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AvgConsentController extends Controller
{
    public function __construct(
        private readonly AvgConsentService $avgConsent,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'akkoord' => ['required', 'boolean'],
        ]);

        $user = $request->user();
        abort_if($user === null, 401);

        $user = $this->avgConsent->registreerKeuze($user, (bool) $data['akkoord']);
        $user->loadMissing('roles', 'functies');

        return response()->json([
            'user' => $user,
            'avg' => $this->avgConsent->payloadVoor($user),
        ]);
    }

    public function defer(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $user = $this->avgConsent->registreerUitstel($user);
        $user->loadMissing('roles', 'functies');

        return response()->json([
            'user' => $user,
            'avg' => $this->avgConsent->payloadVoor($user),
        ]);
    }
}

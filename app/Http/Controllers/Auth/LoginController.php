<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Services\Auth\TwoFactorCodeService;
use App\Services\RoosterAutorisatieService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function login(LoginRequest $request, TwoFactorCodeService $codes): JsonResponse
    {
        if (! Auth::attempt($request->only('email', 'password'))) {
            return response()->json(['message' => __('api.auth.invalid_credentials')], 422);
        }

        /** @var User $user */
        $user = Auth::user();

        if (! $user->active) {
            Auth::logout();

            return response()->json(['message' => __('api.auth.invalid_credentials')], 422);
        }

        $request->session()->regenerate();
        $user->forceFill([
            'last_login_at' => now(),
            'inactiviteit_waarschuwing_at' => null,
        ])->save();

        if (! config('two_factor.enabled')) {
            $request->session()->put('two_factor_verified', true);

            return response()->json(['two_factor_required' => false]);
        }

        $codes->issue($user);

        return response()->json(['two_factor_required' => true]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => __('api.auth.logged_out')]);
    }

    public function me(Request $request): JsonResponse
    {
        if (config('two_factor.enabled') && ! $request->session()->get('two_factor_verified', false)) {
            return response()->json([
                'user' => null,
                'two_factor_pending' => true,
            ]);
        }

        $user = $request->user()?->load('roles', 'functies');
        if ($user !== null) {
            $this->appendRoosterAutorisatieFlags($user);
        }

        return response()->json([
            'user' => $user,
        ]);
    }

    private function appendRoosterAutorisatieFlags(User $user): void
    {
        $authz = app(RoosterAutorisatieService::class);
        $user->setAttribute('beheerbare_gemeente_ids', $authz->beheerbareGemeenteIds($user));
        $user->setAttribute('kan_rooster_beheren', $authz->heeftRoosterOfGemeenteScope($user));
        $user->setAttribute('kan_gemeente_bewerken', $authz->heeftRoosterOfGemeenteScope($user));
        $user->setAttribute('kan_zelf_inschrijven', $authz->kanZichzelfInschrijven($user));
    }
}

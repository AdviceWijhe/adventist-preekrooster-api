<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\SessionInvalidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;

class PasswordResetController extends Controller
{
    public function __construct(
        private readonly SessionInvalidationService $sessionInvalidation,
    ) {}

    public function vergeten(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        Password::sendResetLink($request->only('email'));

        return response()->json([
            'message' => __('api.auth.password_reset_sent'),
        ]);
    }

    public function reset(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill(['password' => $password])->save();
                $this->sessionInvalidation->invalidateFor($user);
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json(['message' => __('api.auth.invalid_reset_link')], 422);
        }

        return response()->json(['message' => __('api.auth.password_changed')]);
    }

    public function aanmaken(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::broker('invites')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill(['password' => $password])->save();
                $this->sessionInvalidation->invalidateFor($user);
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json(['message' => __('api.auth.invalid_invite_link')], 422);
        }

        return response()->json(['message' => __('api.auth.password_set')]);
    }
}

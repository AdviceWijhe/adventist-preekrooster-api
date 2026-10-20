<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\TwoFactorRequest;
use App\Models\TwoFactorCode;
use App\Models\User;
use App\Services\Auth\TwoFactorCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class TwoFactorController extends Controller
{
    public function challenge(TwoFactorRequest $request): JsonResponse
    {
        $user = $request->user();
        $plainCode = $request->string('code')->toString();

        $candidates = TwoFactorCode::query()
            ->where('user_id', $user->id)
            ->where('used', false)
            ->latest()
            ->get();

        $code = $candidates->first(fn (TwoFactorCode $row): bool => Hash::check($plainCode, $row->code));

        if (! $code || ! $code->isValid()) {
            return response()->json(['message' => __('api.auth.invalid_two_factor_code')], 422);
        }

        $code->update(['used' => true]);
        $request->session()->put('two_factor_verified', true);

        return response()->json(['verified' => true]);
    }

    public function resend(Request $request, TwoFactorCodeService $codes): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $codes->issue($user);

        return response()->json(['sent' => true]);
    }
}

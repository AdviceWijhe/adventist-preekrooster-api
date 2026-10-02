<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Mail\TwoFactorCodeMail;
use App\Models\TwoFactorCode;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class TwoFactorCodeService
{
    public function issue(User $user): void
    {
        TwoFactorCode::query()
            ->where('user_id', $user->id)
            ->where('used', false)
            ->delete();

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        TwoFactorCode::query()->create([
            'user_id' => $user->id,
            'code' => Hash::make($code),
            'expires_at' => now()->addMinutes(10),
        ]);

        app()->setLocale(in_array($user->taal, ['nl', 'en'], true) ? $user->taal : app()->getLocale());

        Mail::to($user->email)->send(new TwoFactorCodeMail($code, $user->voornaam));
    }
}

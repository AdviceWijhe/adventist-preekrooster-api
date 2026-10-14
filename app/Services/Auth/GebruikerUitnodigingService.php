<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Mail\WelkomGebruikerMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use RuntimeException;

class GebruikerUitnodigingService
{
    public function stuurUitnodiging(User $user): void
    {
        if (! filled($user->email)) {
            throw new RuntimeException(__('api.beheer.uitnodiging_geen_email'));
        }

        if (! config('mail.features.welcome_user', true)) {
            return;
        }

        $token = Password::broker('invites')->createToken($user);
        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');
        $setPasswordUrl = "{$frontendUrl}/wachtwoord-aanmaken/{$token}";

        Mail::to($user->email)->send(new WelkomGebruikerMail($user, $setPasswordUrl));
    }
}

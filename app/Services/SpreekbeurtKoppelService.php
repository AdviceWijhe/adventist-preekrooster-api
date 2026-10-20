<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\BeurtStatusMeldingMail;
use App\Mail\PredikantUitvraagMail;
use App\Models\Dienst;
use App\Models\Option;
use App\Models\Spreekbeurt;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class SpreekbeurtKoppelService
{
    public function __construct(
        private readonly RoosterMatrixService $roosterMatrixService,
    ) {}

    public function assertRoosterNietVergrendeld(): void
    {
        if (Option::getValue('rooster_vergrendeld') === '1') {
            throw ValidationException::withMessages([
                'dienst' => [__('api.signup.rooster_locked')],
            ]);
        }
    }

    public function assertUserKanPreken(User $user): void
    {
        if (! $user->active) {
            throw ValidationException::withMessages([
                'user' => [__('api.signup.account_inactive')],
            ]);
        }

        if (! $user->kanPreken()) {
            throw ValidationException::withMessages([
                'user' => [__('api.signup.only_speakers')],
            ]);
        }
    }

    public function assertUserBeschikbaarOpDatum(User $user, string $datum): void
    {
        $isOnbeschikbaar = $user->beschikbaarheid()
            ->whereDate('datum_van', '<=', $datum)
            ->whereDate('datum_tot', '>=', $datum)
            ->exists();

        if ($isOnbeschikbaar) {
            throw ValidationException::withMessages([
                'datum' => [__('api.signup.unavailable_date')],
            ]);
        }

        $heeftBeurt = $user->spreekbeurten()
            ->whereHas('dienst', fn ($query) => $query->whereDate('datum', $datum))
            ->where('bevestigd', '!=', 0)
            ->exists();

        if ($heeftBeurt) {
            throw ValidationException::withMessages([
                'datum' => [__('api.signup.already_assigned_date')],
            ]);
        }
    }

    public function assertDienstHeeftOpenPlek(Dienst $dienst): void
    {
        $dienst->loadMissing(['spreekbeurten']);

        if (! empty($dienst->eigeninvulling) && trim((string) $dienst->eigeninvulling) !== '') {
            throw ValidationException::withMessages([
                'dienst' => [__('api.signup.not_open')],
            ]);
        }

        if ($dienst->spreekbeurten->isNotEmpty()) {
            throw ValidationException::withMessages([
                'dienst' => [__('api.signup.already_filled')],
            ]);
        }
    }

    public function assertDienstInGepubliceerdPubliekVenster(Dienst $dienst): void
    {
        $maand = Carbon::parse($dienst->datum)->format('Y-m');

        if (! $this->roosterMatrixService->isMaandGepubliceerdVoorPubliek($maand)) {
            throw ValidationException::withMessages([
                'dienst' => [__('api.signup.outside_published')],
            ]);
        }
    }

    public function assertDienstNietInVerleden(Dienst $dienst): void
    {
        $tz = RoosterMatrixService::TIMEZONE;
        $vandaag = Carbon::now($tz)->startOfDay();
        $dienstDatum = Carbon::parse($dienst->datum, $tz)->startOfDay();

        if ($dienstDatum->lt($vandaag)) {
            throw ValidationException::withMessages([
                'dienst' => [__('api.signup.past_service')],
            ]);
        }
    }

    public function koppel(
        User $spreker,
        Dienst $dienst,
        User $ingevoerdDoor,
        bool $validateRol = true,
        bool $requirePublishedPubliekVenster = false,
        bool $predikantVenster = false,
        bool $autoBevestig = false,
    ): Spreekbeurt {
        $this->assertRoosterNietVergrendeld();
        if ($validateRol) {
            $this->assertUserKanPreken($spreker);
        }
        $this->assertDienstHeeftOpenPlek($dienst);
        if ($requirePublishedPubliekVenster) {
            $this->assertDienstInGepubliceerdPubliekVenster($dienst);
        }
        if ($predikantVenster) {
            $this->assertDienstNietInVerleden($dienst);
        }
        $this->assertUserBeschikbaarOpDatum($spreker, Carbon::parse($dienst->datum)->format('Y-m-d'));

        $attributes = [
            'dienst_id' => $dienst->id,
            'spreker_id' => $spreker->id,
            'ingevoerd_door' => $ingevoerdDoor->id,
        ];
        if ($autoBevestig) {
            $attributes['bevestigd'] = 1;
        }

        $spreekbeurt = Spreekbeurt::query()->create($attributes);

        $spreekbeurt->loadMissing(['spreker', 'dienst.gemeente']);

        if ($autoBevestig) {
            $this->stuurBeurtStatusMelding($spreekbeurt);
        } elseif (filled($spreekbeurt->spreker?->email) && config('mail.features.predikant_uitvraag', true)) {
            app()->setLocale(in_array($spreekbeurt->spreker->taal, ['nl', 'en'], true) ? $spreekbeurt->spreker->taal : app()->getLocale());
            Mail::to($spreekbeurt->spreker->email)->send(new PredikantUitvraagMail($spreekbeurt));
        }

        return $spreekbeurt;
    }

    private function stuurBeurtStatusMelding(Spreekbeurt $spreekbeurt): void
    {
        if (! config('mail.features.beurt_status_melding', true)) {
            return;
        }

        $contactEmail = $spreekbeurt->dienst?->gemeente?->contactpersoon?->email;
        $emails = filled($contactEmail) ? [$contactEmail] : [];

        foreach ($emails as $email) {
            Mail::to($email)->send(new BeurtStatusMeldingMail($spreekbeurt));
        }
    }
}

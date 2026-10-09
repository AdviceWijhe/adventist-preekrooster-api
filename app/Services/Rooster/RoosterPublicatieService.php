<?php

declare(strict_types=1);

namespace App\Services\Rooster;

use App\Mail\PublicatieBekendmakingMail;
use App\Models\Publicatie;
use App\Models\User;
use App\Services\RoosterMatrixService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;

class RoosterPublicatieService
{
    public function publiceerMaand(string $periode, ?int $gepubliceerdDoor = null, bool $stuurBekendmaking = false): Publicatie
    {
        $publicatie = Publicatie::query()->updateOrCreate(
            ['periode' => $periode, 'gemeente_id' => null],
            [
                'gepubliceerd' => true,
                'gepubliceerd_op' => now(),
                'gepubliceerd_door' => $gepubliceerdDoor,
            ]
        );

        if ($stuurBekendmaking) {
            $this->stuurBekendmakingMail($periode);
        }

        return $publicatie;
    }

    public function depublicerMaand(string $periode): Publicatie
    {
        $publicatie = Publicatie::query()
            ->where('periode', $periode)
            ->whereNull('gemeente_id')
            ->firstOrFail();

        $publicatie->update(['gepubliceerd' => false]);

        return $publicatie;
    }

    /**
     * @return list<string> gepubliceerde periodes (YYYY-MM)
     */
    public function publiceerPubliekVenster(?int $gepubliceerdDoor = null, bool $stuurBekendmaking = false): array
    {
        $nu = Carbon::now(RoosterMatrixService::TIMEZONE);
        $periodes = [$nu->format('Y-m')];
        if ($nu->day >= 10) {
            $periodes[] = $nu->copy()->addMonth()->format('Y-m');
        }

        $gedaan = [];
        foreach ($periodes as $periode) {
            $this->publiceerMaand($periode, $gepubliceerdDoor, $stuurBekendmaking);
            $gedaan[] = $periode;
        }

        return $gedaan;
    }

    private function stuurBekendmakingMail(string $periode): void
    {
        if (! config('mail.features.publicatie_bekendmaking', true)) {
            return;
        }

        $ontvangers = User::query()
            ->where('active', true)
            ->whereNotNull('email')
            ->whereHas('roles', fn ($query) => $query->where('slug', 'contactpersoon'))
            ->pluck('email')
            ->unique()
            ->values();

        foreach ($ontvangers as $email) {
            Mail::to($email)->send(new PublicatieBekendmakingMail($periode));
        }
    }
}

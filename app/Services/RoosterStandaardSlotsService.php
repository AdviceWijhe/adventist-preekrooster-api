<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Dienst;
use App\Models\Gemeente;
use App\Models\GemeenteRoosterSluiting;
use Illuminate\Support\Carbon;

final class RoosterStandaardSlotsService
{
    public const DEFAULT_TYPE = 'reguliere_dienst';

    public function ensureVoorMaand(string $ym): void
    {
        $saturdays = RoosterMatrixService::saturdaysInMonth($ym, RoosterMatrixService::TIMEZONE);
        if ($saturdays === []) {
            return;
        }

        $year = (int) substr($ym, 0, 4);
        $month = (int) substr($ym, 5, 2);

        $gemeentes = Gemeente::query()->where('active', true)->get(['id', 'taal']);
        if ($gemeentes->isEmpty()) {
            return;
        }

        $gemeenteIds = $gemeentes->pluck('id')->all();

        $sluitingen = GemeenteRoosterSluiting::query()
            ->whereIn('gemeente_id', $gemeenteIds)
            ->whereYear('datum', $year)
            ->whereMonth('datum', $month)
            ->get()
            ->keyBy(fn (GemeenteRoosterSluiting $s): string => $s->gemeente_id.'|'.$s->datum->format('Y-m-d'));

        $bestaande = Dienst::query()
            ->whereIn('gemeente_id', $gemeenteIds)
            ->whereYear('datum', $year)
            ->whereMonth('datum', $month)
            ->get(['gemeente_id', 'datum'])
            ->keyBy(fn (Dienst $d): string => $d->gemeente_id.'|'.$d->datum->format('Y-m-d'));

        $nu = now();

        foreach ($gemeentes as $gemeente) {
            foreach ($saturdays as $sat) {
                $key = $gemeente->id.'|'.$sat;
                if ($sluitingen->has($key) || $bestaande->has($key)) {
                    continue;
                }

                Dienst::query()->create([
                    'datum' => $sat,
                    'gemeente_id' => $gemeente->id,
                    'type' => self::DEFAULT_TYPE,
                    'taal' => $gemeente->taal === 'en' ? 'en' : 'nl',
                    'dienstwijze' => 'fysiek',
                    'eigeninvulling' => null,
                    'bijzonderheid_id' => null,
                    'created_at' => $nu,
                    'updated_at' => $nu,
                ]);
            }
        }
    }

    public function ensureCel(int $gemeenteId, string $datumIso): ?Dienst
    {
        if ($this->isGesloten($gemeenteId, $datumIso)) {
            return null;
        }

        $bestaand = Dienst::query()
            ->where('gemeente_id', $gemeenteId)
            ->whereDate('datum', $datumIso)
            ->first();

        if ($bestaand !== null) {
            return $bestaand;
        }

        $gemeente = Gemeente::query()->find($gemeenteId);
        if ($gemeente === null || ! $gemeente->active) {
            return null;
        }

        return Dienst::query()->create([
            'datum' => $datumIso,
            'gemeente_id' => $gemeenteId,
            'type' => self::DEFAULT_TYPE,
            'taal' => $gemeente->taal === 'en' ? 'en' : 'nl',
            'dienstwijze' => 'fysiek',
            'eigeninvulling' => null,
            'bijzonderheid_id' => null,
        ]);
    }

    public function isGesloten(int $gemeenteId, string $datumIso): bool
    {
        return GemeenteRoosterSluiting::query()
            ->where('gemeente_id', $gemeenteId)
            ->whereDate('datum', $datumIso)
            ->exists();
    }

    public function sluitDag(int $gemeenteId, string $datumIso): void
    {
        Dienst::query()
            ->where('gemeente_id', $gemeenteId)
            ->whereDate('datum', $datumIso)
            ->delete();

        GemeenteRoosterSluiting::query()->firstOrCreate([
            'gemeente_id' => $gemeenteId,
            'datum' => Carbon::parse($datumIso)->toDateString(),
        ]);
    }

    public function openDag(int $gemeenteId, string $datumIso): Dienst
    {
        GemeenteRoosterSluiting::query()
            ->where('gemeente_id', $gemeenteId)
            ->whereDate('datum', $datumIso)
            ->delete();

        $dienst = $this->ensureCel($gemeenteId, $datumIso);
        if ($dienst === null) {
            throw new \RuntimeException('Kon standaard dienst niet aanmaken.');
        }

        return $dienst;
    }
}

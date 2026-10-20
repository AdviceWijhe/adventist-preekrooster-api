<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Models\Dienst;
use App\Models\Gemeente;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class DashboardStatsService
{
    private const SPARKLINE_MONTHS = 6;

    /**
     * @return array{
     *     predikanten: int,
     *     gemeentes: int,
     *     gebruikers: int,
     *     onbevestigd: int,
     *     sparklines: array<string, list<int>>,
     *     sparkline_period: array{months: int, from: string, to: string},
     *     sparkline_changes: array<string, float|null>
     * }
     */
    public function stats(): array
    {
        $monthEnds = $this->monthEnds();

        $sparklines = [
            'predikanten' => [],
            'gemeentes' => [],
            'gebruikers' => [],
            'onbevestigd' => [],
        ];

        foreach ($monthEnds as $monthEnd) {
            $sparklines['predikanten'][] = $this->activePredikantenAt($monthEnd);
            $sparklines['gemeentes'][] = $this->activeGemeentesAt($monthEnd);
            $sparklines['gebruikers'][] = $this->activeGebruikersAt($monthEnd);
            $sparklines['onbevestigd'][] = $this->onbevestigdInMonth($monthEnd);
        }

        $changes = [
            'predikanten' => $this->percentChange($sparklines['predikanten']),
            'gemeentes' => $this->percentChange($sparklines['gemeentes']),
            'gebruikers' => $this->percentChange($sparklines['gebruikers']),
            'onbevestigd' => $this->percentChange($sparklines['onbevestigd']),
        ];

        return [
            'predikanten' => $sparklines['predikanten'][array_key_last($sparklines['predikanten'])],
            'gemeentes' => $sparklines['gemeentes'][array_key_last($sparklines['gemeentes'])],
            'gebruikers' => $sparklines['gebruikers'][array_key_last($sparklines['gebruikers'])],
            'onbevestigd' => $this->onbevestigdKomendeMaand(),
            'sparklines' => $sparklines,
            'sparkline_period' => [
                'months' => self::SPARKLINE_MONTHS,
                'from' => $monthEnds[0]->format('Y-m'),
                'to' => $monthEnds[array_key_last($monthEnds)]->format('Y-m'),
            ],
            'sparkline_changes' => $changes,
        ];
    }

    /**
     * @param  list<int>  $values
     */
    private function percentChange(array $values): ?float
    {
        if ($values === []) {
            return null;
        }

        $first = $values[0];
        $last = $values[array_key_last($values)];

        if ($first === 0) {
            return $last === 0 ? 0.0 : 100.0;
        }

        return round((($last - $first) / $first) * 100, 1);
    }

    /**
     * @return list<CarbonInterface>
     */
    private function monthEnds(): array
    {
        $ends = [];
        $cursor = now()->startOfMonth();

        for ($i = self::SPARKLINE_MONTHS - 1; $i >= 0; $i--) {
            $ends[] = $cursor->copy()->subMonths($i)->endOfMonth();
        }

        return $ends;
    }

    private function activePredikantenAt(CarbonInterface $until): int
    {
        return User::query()
            ->where('active', true)
            ->where('created_at', '<=', $until)
            ->whereHas('roles', static fn ($query) => $query->where('slug', 'predikant'))
            ->count();
    }

    private function activeGemeentesAt(CarbonInterface $until): int
    {
        return Gemeente::query()
            ->where('active', true)
            ->where('created_at', '<=', $until)
            ->count();
    }

    private function activeGebruikersAt(CarbonInterface $until): int
    {
        return User::query()
            ->where('active', true)
            ->where('created_at', '<=', $until)
            ->count();
    }

    private function onbevestigdKomendeMaand(): int
    {
        $start = now()->startOfDay();
        $end = now()->addMonth()->endOfMonth();

        return $this->onbevestigdBetween($start, $end);
    }

    private function onbevestigdInMonth(CarbonInterface $monthEnd): int
    {
        $start = Carbon::parse($monthEnd)->startOfMonth()->startOfDay();
        $end = Carbon::parse($monthEnd)->endOfMonth()->endOfDay();

        return $this->onbevestigdBetween($start, $end);
    }

    private function onbevestigdBetween(CarbonInterface $start, CarbonInterface $end): int
    {
        return Dienst::query()
            ->whereBetween('datum', [$start, $end])
            ->whereDoesntHave('spreekbeurten', static fn ($query) => $query->where('bevestigd', 1))
            ->count();
    }
}

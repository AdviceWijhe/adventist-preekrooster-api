<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\ChangeLog;
use App\Models\Spreekbeurt;

class SpreekbeurtObserver
{
    public function created(Spreekbeurt $spreekbeurt): void
    {
        ChangeLog::log('Spreker gekoppeld', $spreekbeurt, null, $this->auditSnapshot($spreekbeurt));
    }

    public function updated(Spreekbeurt $spreekbeurt): void
    {
        if ($spreekbeurt->wasChanged('bevestigd')) {
            $label = match ($spreekbeurt->bevestigd) {
                1 => 'bevestigd',
                0 => 'afgewezen',
                default => 'onbekend',
            };
            ChangeLog::log(
                "Preekbeurt {$label}",
                $spreekbeurt,
                ['bevestigd' => $spreekbeurt->getOriginal('bevestigd')],
                ['bevestigd' => $spreekbeurt->bevestigd]
            );
        }
    }

    public function deleted(Spreekbeurt $spreekbeurt): void
    {
        ChangeLog::log('Spreker verwijderd', $spreekbeurt, $this->auditSnapshot($spreekbeurt), null);
    }

    /**
     * @return array{id: int|null, spreker_id: int|null, dienst_id: int|null, bevestigd: int|null}
     */
    private function auditSnapshot(Spreekbeurt $spreekbeurt): array
    {
        return [
            'id' => $spreekbeurt->id,
            'spreker_id' => $spreekbeurt->spreker_id,
            'dienst_id' => $spreekbeurt->dienst_id,
            'bevestigd' => $spreekbeurt->bevestigd,
        ];
    }
}

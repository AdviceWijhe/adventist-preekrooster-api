<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Gemeente;
use App\Models\User;

final class RoosterAutorisatieService
{
    public function isGlobaalBeheerder(User $user): bool
    {
        return $user->isBeheerder();
    }

    /**
     * @return list<int>
     */
    public function beheerbareGemeenteIds(User $user): array
    {
        if ($this->isGlobaalBeheerder($user)) {
            return Gemeente::query()->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        $ids = [];

        if ($user->hasFunctie('contactpersoon')) {
            $ids = array_merge(
                $ids,
                Gemeente::query()->where('contactpersoon_id', $user->id)->pluck('id')->all()
            );
        }

        if ($user->hasFunctie('predikant')) {
            $ids = array_merge(
                $ids,
                Gemeente::query()->where('predikant_id', $user->id)->pluck('id')->all()
            );
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    public function kanRoosterBeheren(User $user, int $gemeenteId): bool
    {
        return in_array($gemeenteId, $this->beheerbareGemeenteIds($user), true);
    }

    public function kanGemeenteBewerken(User $user, int $gemeenteId): bool
    {
        return $this->kanRoosterBeheren($user, $gemeenteId);
    }

    public function kanZichzelfInschrijven(User $user): bool
    {
        return $user->hasAnyFunctie(['spreker', 'predikant'])
            || $user->hasRole('predikant');
    }

    public function heeftRoosterOfGemeenteScope(User $user): bool
    {
        return $this->isGlobaalBeheerder($user) || $this->beheerbareGemeenteIds($user) !== [];
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Bericht;

use App\Mail\BerichtNotificatieMail;
use App\Models\Bericht;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class BerichtService
{
    public function publiceer(Bericht $bericht): Bericht
    {
        if ($bericht->isGepubliceerd()) {
            return $bericht;
        }

        $bericht->forceFill(['gepubliceerd_op' => now()])->save();

        if ($this->moetMailen($bericht)) {
            $this->verstuurMail($bericht);
        }

        return $bericht->fresh(['auteur']);
    }

    /**
     * @return Collection<int, User>
     */
    public function doelgroepGebruikers(Bericht $bericht): Collection
    {
        $query = User::query()->where('active', true);

        if ($bericht->doelgroep === Bericht::DOELGROEP_SPECIFIEK) {
            $this->beperkTotSpecifiekeDoelgroep($query, $bericht);

            return $query->get();
        }

        match ($bericht->doelgroep) {
            Bericht::DOELGROEP_BEHEERDERS => $query->whereHas(
                'roles',
                fn (Builder $q) => $q->whereIn('slug', ['admin', 'beheerder'])
            ),
            Bericht::DOELGROEP_PREDIKANTEN => $query->where(function (Builder $q): void {
                $q->whereHas('roles', fn (Builder $r) => $r->where('slug', 'predikant'))
                    ->orWhereHas('functies', fn (Builder $f) => $f->whereIn('slug', [
                        'predikant', 'spreker',
                    ]));
            }),
            Bericht::DOELGROEP_ALLE => null,
            default => $query->whereRaw('0 = 1'),
        };

        return $query->get();
    }

    public function isZichtbaarVoor(Bericht $bericht, User $user): bool
    {
        if (! $bericht->isGepubliceerd() || ! $user->active) {
            return false;
        }

        if ($bericht->doelgroep === Bericht::DOELGROEP_SPECIFIEK) {
            return $this->gebruikerInSpecifiekeDoelgroep($user, $bericht);
        }

        return $this->gebruikerInDoelgroep($user, $bericht->doelgroep);
    }

    public function markeerGelezen(Bericht $bericht, User $user): void
    {
        if (! $this->isZichtbaarVoor($bericht, $user)) {
            return;
        }

        if ($bericht->gelezenDoor()->where('users.id', $user->id)->exists()) {
            return;
        }

        $bericht->gelezenDoor()->attach($user->id, ['gelezen_op' => now()]);
    }

    public function markeerOngelezen(Bericht $bericht, User $user): void
    {
        if (! $this->isZichtbaarVoor($bericht, $user)) {
            return;
        }

        $bericht->gelezenDoor()->detach($user->id);
    }

    public function verplaatsNaarPrullenbak(Bericht $bericht, User $user): void
    {
        if (! $this->isZichtbaarVoor($bericht, $user)) {
            return;
        }

        if ($bericht->verwijderdDoor()->where('users.id', $user->id)->exists()) {
            $bericht->verwijderdDoor()->updateExistingPivot($user->id, ['verwijderd_op' => now()]);

            return;
        }

        $bericht->verwijderdDoor()->attach($user->id, ['verwijderd_op' => now()]);
    }

    public function herstelUitPrullenbak(Bericht $bericht, User $user): void
    {
        if (! $this->isZichtbaarVoor($bericht, $user)) {
            return;
        }

        $bericht->verwijderdDoor()->detach($user->id);
    }

    public function opschonenPrullenbak(int $dagen = 30): int
    {
        return DB::table('bericht_verwijderd')
            ->where('verwijderd_op', '<', now()->subDays($dagen))
            ->delete();
    }

    public function unreadCount(User $user): int
    {
        return $this->inboxQuery($user)
            ->whereDoesntHave('gelezenDoor', fn (Builder $q) => $q->where('users.id', $user->id))
            ->count();
    }

    /**
     * @return Builder<Bericht>
     */
    public function inboxQuery(User $user): Builder
    {
        return $this->zichtbareBerichtenQuery($user)
            ->whereDoesntHave('verwijderdDoor', fn (Builder $q) => $q->where('users.id', $user->id));
    }

    /**
     * @return Builder<Bericht>
     */
    public function prullenbakQuery(User $user): Builder
    {
        return $this->zichtbareBerichtenQuery($user)
            ->whereHas('verwijderdDoor', fn (Builder $q) => $q->where('users.id', $user->id));
    }

    /**
     * @return Builder<Bericht>
     */
    private function zichtbareBerichtenQuery(User $user): Builder
    {
        $gemeenteIds = $user->gemeentes()->pluck('gemeentes.id')->all();
        if ($user->gemeente_id !== null) {
            $gemeenteIds[] = $user->gemeente_id;
        }
        $gemeenteIds = array_values(array_unique($gemeenteIds));

        return Bericht::query()
            ->whereNotNull('gepubliceerd_op')
            ->where('gepubliceerd_op', '<=', now())
            ->where(function (Builder $q) use ($user, $gemeenteIds): void {
                $q->where('doelgroep', Bericht::DOELGROEP_ALLE);

                if ($this->gebruikerInDoelgroep($user, Bericht::DOELGROEP_PREDIKANTEN)) {
                    $q->orWhere('doelgroep', Bericht::DOELGROEP_PREDIKANTEN);
                }

                if ($this->gebruikerInDoelgroep($user, Bericht::DOELGROEP_BEHEERDERS)) {
                    $q->orWhere('doelgroep', Bericht::DOELGROEP_BEHEERDERS);
                }

                $q->orWhere(function (Builder $specifiek) use ($user, $gemeenteIds): void {
                    $specifiek->where('doelgroep', Bericht::DOELGROEP_SPECIFIEK)
                        ->where(function (Builder $ids) use ($user, $gemeenteIds): void {
                            $ids->whereJsonContains('gebruiker_ids', $user->id);

                            foreach ($gemeenteIds as $gemeenteId) {
                                $ids->orWhereJsonContains('gemeente_ids', $gemeenteId);
                            }
                        });
                });
            })
            ->latest('gepubliceerd_op');
    }

    /**
     * @param  Builder<User>  $query
     */
    private function beperkTotSpecifiekeDoelgroep(Builder $query, Bericht $bericht): void
    {
        $gemeenteIds = $bericht->gemeente_ids ?? [];
        $gebruikerIds = $bericht->gebruiker_ids ?? [];

        $query->where(function (Builder $q) use ($gemeenteIds, $gebruikerIds): void {
            if ($gemeenteIds !== []) {
                $q->orWhereHas(
                    'gemeentes',
                    fn (Builder $g) => $g->whereIn('gemeentes.id', $gemeenteIds)
                )->orWhereIn('gemeente_id', $gemeenteIds);
            }

            if ($gebruikerIds !== []) {
                $q->orWhereIn('id', $gebruikerIds);
            }

            if ($gemeenteIds === [] && $gebruikerIds === []) {
                $q->whereRaw('0 = 1');
            }
        });
    }

    private function gebruikerInSpecifiekeDoelgroep(User $user, Bericht $bericht): bool
    {
        $gebruikerIds = $bericht->gebruiker_ids ?? [];
        if (in_array($user->id, $gebruikerIds, true)) {
            return true;
        }

        $gemeenteIds = $bericht->gemeente_ids ?? [];
        if ($gemeenteIds === []) {
            return false;
        }

        if ($user->gemeente_id !== null && in_array($user->gemeente_id, $gemeenteIds, true)) {
            return true;
        }

        return $user->gemeentes()->whereIn('gemeentes.id', $gemeenteIds)->exists();
    }

    private function moetMailen(Bericht $bericht): bool
    {
        if (! config('mail.features.bericht_notificatie', true)) {
            return false;
        }

        return in_array($bericht->kanaal, [Bericht::KANAAL_EMAIL, Bericht::KANAAL_BEIDE], true);
    }

    private function verstuurMail(Bericht $bericht): void
    {
        foreach ($this->doelgroepGebruikers($bericht) as $gebruiker) {
            if ($gebruiker->email === null || $gebruiker->email === '') {
                continue;
            }

            Mail::to($gebruiker->email)->queue(new BerichtNotificatieMail($bericht));
        }
    }

    private function gebruikerInDoelgroep(User $user, string $doelgroep): bool
    {
        return match ($doelgroep) {
            Bericht::DOELGROEP_ALLE => true,
            Bericht::DOELGROEP_PREDIKANTEN => $user->hasRole('predikant')
                || $user->hasAnyFunctie(['predikant', 'spreker']),
            Bericht::DOELGROEP_BEHEERDERS => $user->isBeheerder(),
            default => false,
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Avg;

use App\Models\ChangeLog;
use App\Models\User;
use App\Services\Instellingen\InstellingenService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

final class AvgConsentService
{
    public function __construct(
        private readonly InstellingenService $instellingen,
    ) {}

    public function isEnabled(): bool
    {
        return $this->instellingen->avg()['enabled'];
    }

    public function dagenTermijn(): int
    {
        return $this->instellingen->avg()['dagen'];
    }

    public function heeftDeelbareTelefoon(User $user): bool
    {
        return filled(trim((string) ($user->telefoonnummer ?? '')))
            || filled(trim((string) ($user->mobiel ?? '')));
    }

    public function heeftGeldigAvgAkkoord(User $user): bool
    {
        return $user->avg_consent_at !== null && $this->heeftDeelbareTelefoon($user);
    }

    public function isVereistVoor(User $user): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        if ($user->isBeheerder()) {
            return false;
        }

        return ! $this->heeftGeldigAvgAkkoord($user);
    }

    public function trekConsentInBijOntbrekendeTelefoon(User $user): User
    {
        if ($user->avg_consent_at === null || $this->heeftDeelbareTelefoon($user)) {
            return $user;
        }

        $user->forceFill([
            'avg_consent_at' => null,
            'avg_refused_at' => null,
            'avg_deferred_at' => null,
        ])->save();

        ChangeLog::log('AVG-toestemming ingetrokken (geen telefoonnummer)', $user);

        return $user->fresh() ?? $user;
    }

    public function dagenResterend(User $user): ?int
    {
        if ($user->avg_refused_at === null) {
            return null;
        }

        $deadline = $user->avg_refused_at->copy()->startOfDay()->addDays($this->dagenTermijn());
        $resterend = (int) now()->startOfDay()->diffInDays($deadline, false);

        return max(0, $resterend);
    }

    /**
     * @return array{
     *     vereist: bool,
     *     geweigerd: bool,
     *     dagen_resterend: int|null,
     *     dagen_termijn: int,
     *     titel: string,
     *     tekst_akkoord: string,
     *     tekst_weigering: string,
     *     taal: string
     * }
     */
    public function payloadVoor(User $user): array
    {
        $avg = $this->instellingen->avgVoorTaal((string) ($user->taal ?? 'nl'));
        $vereist = $this->isVereistVoor($user);
        $resterend = $this->dagenResterend($user);
        $dagenVoorTekst = $resterend ?? $avg['dagen'];

        return [
            'vereist' => $vereist,
            'geweigerd' => $user->avg_refused_at !== null && $user->avg_consent_at === null,
            'dagen_resterend' => $resterend,
            'dagen_termijn' => $this->dagenTermijn(),
            'taal' => ($user->taal === 'en') ? 'en' : 'nl',
            'titel' => $avg['titel'],
            'tekst_akkoord' => $avg['tekst_akkoord'],
            'tekst_weigering' => str_replace('{dagen}', (string) $dagenVoorTekst, $avg['tekst_weigering']),
        ];
    }

    public function registreerKeuze(User $user, bool $akkoord): User
    {
        if ($user->isBeheerder()) {
            return $user;
        }

        if ($akkoord) {
            if (! $this->heeftDeelbareTelefoon($user)) {
                throw ValidationException::withMessages([
                    'akkoord' => [__('api.avg.consent_requires_phone')],
                ]);
            }

            $user->forceFill([
                'avg_consent_at' => now(),
                'avg_refused_at' => null,
                'avg_deferred_at' => null,
            ])->save();

            return $user->fresh() ?? $user;
        }

        $updates = [];
        if ($user->avg_refused_at === null) {
            $updates['avg_refused_at'] = now();
        }
        $updates['avg_consent_at'] = null;
        $updates['avg_deferred_at'] = null;

        if ($updates !== []) {
            $user->forceFill($updates)->save();
        }

        return $user->fresh() ?? $user;
    }

    public function registreerUitstel(User $user): User
    {
        if ($user->isBeheerder() || ! $this->isEnabled() || $user->avg_consent_at !== null) {
            return $user;
        }

        if ($user->avg_refused_at !== null) {
            return $user;
        }

        $user->forceFill(['avg_deferred_at' => now()])->save();

        return $user->fresh() ?? $user;
    }

    /**
     * Verbergt contactvelden voor andere niet-beheer-viewers zonder AVG-akkoord.
     */
    public function redactContactVoorViewer(User $target, ?User $viewer): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        if ($viewer !== null && ($viewer->id === $target->id || $viewer->isBeheerder())) {
            return;
        }

        if ($this->heeftGeldigAvgAkkoord($target)) {
            return;
        }

        $target->setAttribute('email', null);
        $target->setAttribute('telefoonnummer', null);
        $target->setAttribute('mobiel', null);
    }

    public function deactivatieDeadline(User $user): ?Carbon
    {
        if ($user->avg_refused_at === null) {
            return null;
        }

        return $user->avg_refused_at->copy()->startOfDay()->addDays($this->dagenTermijn());
    }

    public const STATUS_FILTER_PENDING = 'pending';

    public const STATUS_FILTER_OPEN = 'open';

    public const STATUS_FILTER_WACHT = 'wacht';

    public const STATUS_FILTER_GEWEIGERD = 'geweigerd';

    public const STATUS_FILTER_AKKOORD = 'akkoord';

    public const STATUS_FILTER_ALL = 'all';

    /**
     * @return list<array{
     *     id: int,
     *     voornaam: string|null,
     *     tussenvoegsel: string|null,
     *     achternaam: string|null,
     *     email: string|null,
     *     status: 'wacht'|'open'|'geweigerd'|'akkoord',
     *     dagen_resterend: int|null,
     *     avg_refused_at: string|null,
     *     avg_consent_at: string|null,
     *     initialen: string|null,
     *     photo_url: string|null
     * }>
     */
    public function statusOverzicht(string $filter = self::STATUS_FILTER_PENDING): array
    {
        $query = User::query()
            ->whereDoesntHave('roles', fn (Builder $roleQuery) => $roleQuery->whereIn('slug', ['admin', 'beheerder']));

        match ($filter) {
            self::STATUS_FILTER_OPEN => $query
                ->whereNull('avg_consent_at')
                ->whereNull('avg_refused_at')
                ->whereNotNull('avg_deferred_at'),
            self::STATUS_FILTER_WACHT => $query
                ->whereNull('avg_refused_at')
                ->where(function (Builder $wacht): void {
                    $wacht->where(function (Builder $normaal): void {
                        $normaal
                            ->whereNull('avg_consent_at')
                            ->whereNull('avg_deferred_at');
                    })->orWhere(function (Builder $stale): void {
                        $stale->whereNotNull('avg_consent_at');
                        $this->waarGeenDeelbareTelefoon($stale);
                    });
                }),
            self::STATUS_FILTER_GEWEIGERD => $query
                ->whereNull('avg_consent_at')
                ->whereNotNull('avg_refused_at'),
            self::STATUS_FILTER_AKKOORD => tap($query, function (Builder $akkoord): void {
                $akkoord->whereNotNull('avg_consent_at');
                $this->waarDeelbareTelefoon($akkoord);
            }),
            self::STATUS_FILTER_ALL => null,
            default => $query->where(function (Builder $pending): void {
                $pending->whereNull('avg_consent_at')->orWhere(function (Builder $stale): void {
                    $stale->whereNotNull('avg_consent_at');
                    $this->waarGeenDeelbareTelefoon($stale);
                });
            }),
        };

        return $query
            ->get()
            ->map(fn (User $user): array => $this->statusRijVoor($user))
            ->sort($this->statusSorter(...))
            ->values()
            ->all();
    }

    public function resetConsent(User $user): User
    {
        if ($user->isBeheerder()) {
            throw ValidationException::withMessages([
                'gebruiker' => [__('api.beheer.avg_reset_beheerder_forbidden')],
            ]);
        }

        $user->forceFill([
            'avg_consent_at' => null,
            'avg_refused_at' => null,
            'avg_deferred_at' => null,
        ])->save();

        ChangeLog::log('AVG-toestemming gereset', $user);

        return $user->fresh() ?? $user;
    }

    /**
     * @return array{
     *     id: int,
     *     voornaam: string|null,
     *     tussenvoegsel: string|null,
     *     achternaam: string|null,
     *     email: string|null,
     *     status: 'wacht'|'open'|'geweigerd'|'akkoord',
     *     dagen_resterend: int|null,
     *     avg_refused_at: string|null,
     *     avg_consent_at: string|null,
     *     initialen: string|null,
     *     photo_url: string|null
     * }
     */
    private function statusRijVoor(User $user): array
    {
        $profiel = [
            'id' => $user->id,
            'voornaam' => $user->voornaam,
            'tussenvoegsel' => $user->tussenvoegsel,
            'achternaam' => $user->achternaam,
            'email' => $user->email,
            'initialen' => $user->initialen,
            'photo_url' => $user->photo_url,
        ];

        if ($this->heeftGeldigAvgAkkoord($user)) {
            return [
                ...$profiel,
                'status' => 'akkoord',
                'dagen_resterend' => null,
                'avg_refused_at' => null,
                'avg_consent_at' => $user->avg_consent_at->toIso8601String(),
            ];
        }

        $geweigerd = $user->avg_refused_at !== null;
        $status = 'wacht';
        if ($geweigerd) {
            $status = 'geweigerd';
        } elseif ($user->avg_deferred_at !== null) {
            $status = 'open';
        }

        return [
            ...$profiel,
            'status' => $status,
            'dagen_resterend' => $geweigerd ? $this->dagenResterend($user) : $this->dagenTermijn(),
            'avg_refused_at' => $user->avg_refused_at?->toIso8601String(),
            'avg_consent_at' => null,
        ];
    }

    private function waarDeelbareTelefoon(Builder $query): void
    {
        $query->where(function (Builder $q): void {
            $q->whereRaw("TRIM(COALESCE(telefoonnummer, '')) != ''")
                ->orWhereRaw("TRIM(COALESCE(mobiel, '')) != ''");
        });
    }

    private function waarGeenDeelbareTelefoon(Builder $query): void
    {
        $query->whereRaw("TRIM(COALESCE(telefoonnummer, '')) = ''")
            ->whereRaw("TRIM(COALESCE(mobiel, '')) = ''");
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    private function statusSorter(array $a, array $b): int
    {
        $order = ['geweigerd' => 0, 'open' => 1, 'wacht' => 2, 'akkoord' => 3];
        $rankA = $order[$a['status']] ?? 3;
        $rankB = $order[$b['status']] ?? 3;

        if ($rankA !== $rankB) {
            return $rankA <=> $rankB;
        }

        if ($a['status'] === 'geweigerd') {
            return ($a['dagen_resterend'] ?? 0) <=> ($b['dagen_resterend'] ?? 0);
        }

        return 0;
    }
}

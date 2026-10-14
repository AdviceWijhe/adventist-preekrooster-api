<?php

declare(strict_types=1);

namespace App\Services\Gebruiker;

use App\Models\Gemeente;
use App\Models\User;
use App\Services\Avg\AvgConsentService;
use Illuminate\Database\Eloquent\Collection;

final class ContactpersoonService
{
    /** Kerkelijke functies waarvan contactgegevens via AVG gedeeld mogen worden. */
    private const FUNCTIE_SLUGS = ['contactpersoon', 'predikant', 'spreker'];

    public function __construct(
        private readonly AvgConsentService $avgConsent,
    ) {}

    /**
     * @return Collection<int, User>
     */
    public function metZichtbareContactgegevens(string $search = ''): Collection
    {
        $search = trim($search);

        $users = User::query()
            ->where('active', true)
            ->where(function ($query): void {
                $query
                    ->whereHas('functies', static function ($functieQuery): void {
                        $functieQuery->whereIn('slug', self::FUNCTIE_SLUGS);
                    })
                    ->orWhereIn('id', Gemeente::query()->whereNotNull('contactpersoon_id')->select('contactpersoon_id'));
            })
            ->when($this->avgConsent->isEnabled(), static function ($query): void {
                $query->whereNotNull('avg_consent_at');
            })
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery
                        ->where('voornaam', 'like', "%{$search}%")
                        ->orWhere('tussenvoegsel', 'like', "%{$search}%")
                        ->orWhere('achternaam', 'like', "%{$search}%");
                });
            })
            ->orderBy('achternaam')
            ->orderBy('voornaam')
            ->get([
                'id',
                'voornaam',
                'tussenvoegsel',
                'achternaam',
                'initialen',
                'photo',
                'email',
                'telefoonnummer',
                'mobiel',
                'avg_consent_at',
            ]);

        if ($this->avgConsent->isEnabled()) {
            return $users
                ->filter(fn (User $user): bool => $this->avgConsent->heeftGeldigAvgAkkoord($user))
                ->values();
        }

        return $users;
    }
}

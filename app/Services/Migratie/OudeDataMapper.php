<?php

declare(strict_types=1);

namespace App\Services\Migratie;

final class OudeDataMapper
{
    public const DISTRICT_ZERO_ID = 3;

    /**
     * @return list<array{naam: string, slug: string}>
     */
    public function talen(): array
    {
        return [
            ['naam' => 'Nederlands', 'slug' => 'nederlands'],
            ['naam' => 'Engels', 'slug' => 'engels'],
            ['naam' => 'Duits', 'slug' => 'duits'],
            ['naam' => 'Spaans', 'slug' => 'spaans'],
            ['naam' => 'Papiaments', 'slug' => 'papiaments'],
            ['naam' => 'Portugees', 'slug' => 'portugees'],
        ];
    }

    public function geslacht(int|string|null $raw): string
    {
        return match ((int) $raw) {
            0 => 'm',
            1 => 'v',
            default => 'o',
        };
    }

    /**
     * @return array{rollen: list<string>, functie: ?string, spreekniveau: ?string, landelijk_actief: bool}
     */
    public function identiteit(int|string|null $functie, bool $isAdmin): array
    {
        $id = (int) $functie;

        $rollen = ['gebruiker'];
        if ($isAdmin) {
            $rollen[] = 'admin';
        }

        $functieSlug = match ($id) {
            1, 2 => 'predikant',
            3, 4, 6, 8 => 'spreker',
            7 => 'contactpersoon',
            default => null,
        };

        $spreekniveau = match ($functieSlug) {
            'predikant' => 'predikant',
            'spreker' => 'spreker',
            default => null,
        };

        return [
            'rollen' => $rollen,
            'functie' => $functieSlug,
            'spreekniveau' => $spreekniveau,
            'landelijk_actief' => in_array($id, [3, 4], true),
        ];
    }

    public function userTaal(?string $raw): string
    {
        $taal = strtolower(trim((string) $raw));

        return match (true) {
            str_starts_with($taal, 'en') => 'en',
            str_starts_with($taal, 'nl') => 'nl',
            default => 'nl',
        };
    }

    public function gemeenteTaal(?string $raw): string
    {
        $taal = strtoupper(trim((string) $raw));

        return match ($taal) {
            'NL', '' => 'nl',
            'GB' => 'en',
            'PRT' => 'pt',
            'PAP' => 'pap',
            'GHA' => 'gha',
            default => 'nl',
        };
    }

    public function taalSlug(?string $language): ?string
    {
        return match (strtolower(trim((string) $language))) {
            'dutch', 'nederlands', 'nl' => 'nederlands',
            'english', 'engels', 'en' => 'engels',
            'spanish', 'spaans', 'es' => 'spaans',
            'papiamentu', 'papiaments', 'pap' => 'papiaments',
            'portuguese', 'portugees', 'pt' => 'portugees',
            'german', 'duits', 'de' => 'duits',
            default => null,
        };
    }

    public function dienstTaal(?string $language): string
    {
        return match (strtolower(trim((string) $language))) {
            '', 'dutch', 'nederlands', 'nl' => 'nl',
            'english', 'engels', 'en' => 'en',
            'papiamentu', 'papiaments', 'pap' => 'pap',
            'portuguese', 'portugees', 'pt', 'prt' => 'pt',
            'spanish', 'spaans', 'es' => 'es',
            'ghanaian', 'ghanees', 'gha' => 'gha',
            'german', 'duits', 'de' => 'de',
            default => mb_substr(strtolower(trim((string) $language)), 0, 10),
        };
    }

    public function begintijd(?string $raw, string $fallback): string
    {
        $tijd = str_replace('.', ':', trim((string) $raw));
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $tijd, $match) !== 1) {
            return $fallback;
        }

        $uur = (int) $match[1];
        $minuut = (int) $match[2];
        if ($uur > 23 || $minuut > 59) {
            return $fallback;
        }

        return sprintf('%02d:%02d', $uur, $minuut);
    }

    public function districtId(int|string|null $raw): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $id = (int) $raw;

        return $id === 0 ? self::DISTRICT_ZERO_ID : $id;
    }

    public function bijzonderheidId(int|string|null $kind): ?int
    {
        $id = (int) $kind;

        return $id > 0 ? $id : null;
    }

    public function bevestigd(int|string|null $confirmed): ?int
    {
        return (int) $confirmed === 1 ? 1 : null;
    }

    /**
     * @param  list<array{id: int, kind: int|string|null}>  $rijen
     * @return array{id: int, kind: int|string|null}
     */
    public function kiesPreekbeurt(array $rijen): array
    {
        $kandidaten = array_values(array_filter(
            $rijen,
            fn (array $rij): bool => (int) ($rij['kind'] ?? 0) > 0,
        ));

        if ($kandidaten === []) {
            $kandidaten = $rijen;
        }

        usort($kandidaten, fn (array $a, array $b): int => $b['id'] <=> $a['id']);

        return $kandidaten[0];
    }

    public function naam(?string $raw): string
    {
        $naam = trim((string) $raw);

        return $naam === '' ? 'Onbekend' : $naam;
    }

    public function email(?string $raw, int $id): string
    {
        $email = trim((string) $raw);

        return $email === '' ? "user_{$id}@migratie.local" : $email;
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Dienst;
use App\Models\Gemeente;
use App\Models\Publicatie;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class RoosterMatrixService
{
    public const TIMEZONE = 'Europe/Amsterdam';

    private const TYPE_ORDER = [
        'dienst' => 0,
        'reguliere_dienst' => 1,
        'bijzonder' => 2,
        // Legacy
        'sabbatschool' => 0,
        'eredienst' => 1,
        'speciaal' => 2,
    ];

    /**
     * @return list<string> ISO-datum (Y-m-d) alle zaterdagen in YYYY-MM, in gegeven tz.
     */
    public static function saturdaysInMonth(string $ym, string $timezone): array
    {
        $start = CarbonImmutable::createFromFormat('Y-m', $ym, $timezone)->startOfMonth();
        $end = $start->endOfMonth();
        $out = [];
        $d = $start;
        while ($d->lessThanOrEqualTo($end)) {
            if ($d->isSaturday()) {
                $out[] = $d->format('Y-m-d');
            }
            $d = $d->addDay();
        }

        return $out;
    }

    /**
     * Standaard weergavemaand: huidige maand zolang de laatste zaterdag nog komt;
     * daarna automatisch de volgende maand (Europe/Amsterdam).
     */
    public static function standaardWeergaveMaand(?Carbon $nu = null): string
    {
        $tz = self::TIMEZONE;
        $nu = ($nu ?? Carbon::now($tz))->timezone($tz);
        $huidige = $nu->format('Y-m');
        $saturdays = self::saturdaysInMonth($huidige, $tz);

        if ($saturdays === []) {
            return $huidige;
        }

        $lastSaturday = $saturdays[array_key_last($saturdays)];

        if ($nu->format('Y-m-d') > $lastSaturday) {
            return $nu->copy()->addMonth()->format('Y-m');
        }

        return $huidige;
    }

    /**
     * Publiek: huidige maand t/m volgende maand (max. 2 maanden zichtbaar zonder login).
     *
     * @return array{maand: string|null, eerste_maand: string, laatste_maand: string, account_vereist_voor_volledig: true}
     */
    public function publiekStandaardMaand(): array
    {
        $tz = self::TIMEZONE;
        $nu = Carbon::now($tz);
        $huidige = $nu->format('Y-m');
        $volgende = $nu->copy()->addMonth()->format('Y-m');

        $default = self::standaardWeergaveMaand($nu);
        if (! $this->isGepubliceerdInPubliekVenster($default) && $this->isGepubliceerdInPubliekVenster($volgende)) {
            $default = $volgende;
        } elseif (! $this->isGepubliceerdInPubliekVenster($default) && $this->isGepubliceerdInPubliekVenster($huidige)) {
            $default = $huidige;
        }

        return [
            'maand' => $default,
            'eerste_maand' => $huidige,
            'laatste_maand' => $volgende,
            'account_vereist_voor_volledig' => true,
        ];
    }

    /**
     * Ingelogde gebruiker: volledig rooster (zelfde navigatiegrenzen als beheer).
     *
     * @return array{maand: string, eerste_maand: string, laatste_maand: string}
     */
    public function ingelogdStandaardMaand(): array
    {
        return $this->beheerStandaardMaand();
    }

    /**
     * Beheer: geselecteerde maand = huidige kalendermaand (Amsterdam). Navigatiegrenzen: vanaf de
     * vroegste maand met diensten t/m de laatste, altijd de huidige maand inbegrepen (min/max op Y-m).
     */
    public function beheerStandaardMaand(): array
    {
        $tz = self::TIMEZONE;
        $nu = Carbon::now($tz);
        $weergaveMaand = self::standaardWeergaveMaand($nu);

        $maxDatum = Dienst::query()->max('datum');
        $minDatum = Dienst::query()->min('datum');
        $maxDienstM = $maxDatum ? Carbon::parse($maxDatum, $tz)->format('Y-m') : null;
        $minDienstM = $minDatum ? Carbon::parse($minDatum, $tz)->format('Y-m') : null;

        $eerste = $weergaveMaand;
        $laatste = $weergaveMaand;
        if ($minDienstM !== null) {
            $eerste = min($eerste, $minDienstM);
        }
        if ($maxDienstM !== null) {
            $laatste = max($laatste, $maxDienstM);
        }

        return [
            'maand' => $weergaveMaand,
            'eerste_maand' => $eerste,
            'laatste_maand' => $laatste,
        ];
    }

    /**
     * @return array{maand: string, gepubliceerd: bool, message?: string, saturdays: list<string>, districts: list<array<string, mixed>>}
     */
    public function buildVoorPubliek(string $maand): array
    {
        $saturdays = self::saturdaysInMonth($maand, self::TIMEZONE);

        if (! $this->isPubliekToegestaneMaand($maand)) {
            return [
                'maand' => $maand,
                'gepubliceerd' => false,
                'message_key' => 'public_window_only',
                'message' => __('api.rooster.public_window_only'),
                'saturdays' => $saturdays,
                'districts' => [],
            ];
        }

        $isGepubliceerd = Publicatie::query()
            ->where('periode', $maand)
            ->whereNull('gemeente_id')
            ->where('gepubliceerd', true)
            ->exists();

        if (! $isGepubliceerd) {
            return [
                'maand' => $maand,
                'gepubliceerd' => false,
                'message_key' => 'not_published',
                'message' => __('api.rooster.not_published'),
                'saturdays' => $saturdays,
                'districts' => [],
            ];
        }

        return [
            'maand' => $maand,
            'gepubliceerd' => true,
            'saturdays' => $saturdays,
            'districts' => $this->buildDistricts($maand, $saturdays, false),
        ];
    }

    public function isMaandGepubliceerdVoorPubliek(string $maand): bool
    {
        return $this->isGepubliceerdInPubliekVenster($maand);
    }

    /**
     * @return array{maand: string, gepubliceerd: true, saturdays: list<string>, districts: list<array<string, mixed>>}
     */
    public function buildVoorIngelogd(string $maand): array
    {
        return $this->buildVoorBeheer($maand);
    }

    /**
     * @return array{maand: string, gepubliceerd: true, saturdays: list<string>, districts: list<array<string, mixed>>}
     */
    public function buildVoorBeheer(string $maand): array
    {
        $saturdays = self::saturdaysInMonth($maand, self::TIMEZONE);

        return [
            'maand' => $maand,
            'gepubliceerd' => true,
            'saturdays' => $saturdays,
            'districts' => $this->buildDistricts($maand, $saturdays, true),
        ];
    }

    /**
     * @param  list<string>  $saturdays
     * @return list<array<string, mixed>>
     */
    private function buildDistricts(string $maand, array $saturdays, bool $metSprekerStatusInMatrix): array
    {
        $saturdaySet = array_flip($saturdays);
        $year = (int) substr($maand, 0, 4);
        $month = (int) substr($maand, 5, 2);

        $diensten = Dienst::query()
            ->whereYear('datum', $year)
            ->whereMonth('datum', $month)
            ->with(['bijzonderheid:id,naam', 'spreekbeurten.spreker:id,voornaam,tussenvoegsel,achternaam,photo,initialen'])
            ->get()
            ->filter(function (Dienst $d) use ($saturdaySet): bool {
                $key = $d->datum->format('Y-m-d');

                return isset($saturdaySet[$key]);
            });

        $byGemeenteDatum = $diensten->groupBy(
            fn (Dienst $d) => $d->gemeente_id.'|'.$d->datum->format('Y-m-d')
        );

        $gemeentes = Gemeente::query()
            ->with('district')
            ->where('active', true)
            ->where(function ($query): void {
                $query->whereNull('district_id')
                    ->orWhereHas('district', fn ($district) => $district->where('visible', true));
            })
            ->orderBy('volgorde')
            ->orderBy('naam')
            ->get();

        $byDistrict = $gemeentes->groupBy(
            static fn (Gemeente $g) => $g->district_id === null ? 'zonder' : (string) $g->district_id
        );

        $districtRows = [];

        $ordered = $byDistrict->sortKeysUsing(static function (string|int $a, string|int $b): int {
            if ($a === 'zonder' && $b === 'zonder') {
                return 0;
            }
            if ($a === 'zonder') {
                return 1;
            }
            if ($b === 'zonder') {
                return -1;
            }

            return (int) $a <=> (int) $b;
        });

        foreach ($ordered as $districtKey => $list) {
            if ($districtKey === 'zonder') {
                $districtId = null;
                $districtNaam = __('api.rooster.no_district');
            } else {
                $districtId = (int) $districtKey;
                $first = $list->first();
                $districtNaam = $first?->district?->naam ?? __('api.rooster.district_named', ['id' => $districtId]);
            }
            $gemeenteRows = [];
            foreach ($list as $gemeente) {
                $cellen = [];
                foreach ($saturdays as $sat) {
                    $key = $gemeente->id.'|'.$sat;
                    $items = $byGemeenteDatum->get($key) ?? collect();
                    $dienstenInCel = $this->sortDienstenInCel(
                        $items instanceof Collection ? $items : collect([$items])
                    );
                    $regels = [];
                    foreach ($dienstenInCel as $d) {
                        $weergave = $this->weergaveVoorCel($d, ! $metSprekerStatusInMatrix);
                        $regel = [
                            'dienst_id' => $d->id,
                            'type' => $d->type,
                            'weergave' => $weergave,
                            'open_plek' => $this->isOpenPlek($d, $weergave),
                        ];
                        $sprekerPhotoUrl = $this->sprekerPhotoUrlVoorCel($d, ! $metSprekerStatusInMatrix);
                        if ($sprekerPhotoUrl !== null && $weergave !== '—') {
                            $regel['spreker_photo_url'] = $sprekerPhotoUrl;
                        }
                        if ($d->bijzonderheid) {
                            $regel['bijzonderheid'] = $d->bijzonderheid->naam;
                        }
                        if ($metSprekerStatusInMatrix) {
                            $status = $this->sprekerStatusVoorMatrix($d);
                            if ($status !== null) {
                                $regel['spreker_status'] = $status;
                            }
                        }
                        $regels[] = $regel;
                    }
                    $cellen[$sat] = ['diensten' => $regels];
                }
                $gemeenteRows[] = [
                    'id' => $gemeente->id,
                    'naam' => $gemeente->naam,
                    'church_plant' => (bool) $gemeente->church_plant,
                    'kerk' => $this->nullableTrimmedString($gemeente->kerk),
                    'begintijd_eredienst' => $this->normaliseerTijdVoorMatrix($gemeente->begintijd_ochtend),
                    'begintijd_sabbatschool' => $this->normaliseerTijdVoorMatrix($gemeente->begintijd_avond),
                    'adres' => $this->nullableTrimmedString($gemeente->adres),
                    'postcode' => $this->nullableTrimmedString($gemeente->postcode),
                    'plaats' => $this->nullableTrimmedString($gemeente->plaats),
                    'website_url' => $this->nullableTrimmedString($gemeente->website_url),
                    'livestream_url' => $this->nullableTrimmedString($gemeente->livestream_url),
                    'cellen' => $cellen,
                ];
            }
            if ($list->isNotEmpty()) {
                $districtRows[] = [
                    'id' => $districtId !== null ? (int) $districtId : null,
                    'naam' => $districtNaam,
                    'gemeentes' => $gemeenteRows,
                ];
            }
        }

        usort(
            $districtRows,
            static function (array $a, array $b): int {
                if ($a['id'] === null && $b['id'] !== null) {
                    return 1;
                }
                if ($b['id'] === null && $a['id'] !== null) {
                    return -1;
                }

                return ($a['naam'] ?? '') <=> ($b['naam'] ?? '');
            }
        );

        return $districtRows;
    }

    /**
     * @param  Collection<int, Dienst>  $items
     * @return Collection<int, Dienst>
     */
    private function sortDienstenInCel(Collection $items): Collection
    {
        return $items->sortBy(function (Dienst $d): int {
            $t = (string) $d->type;

            return self::TYPE_ORDER[$t] ?? 99;
        })->values();
    }

    /**
     * Status t.o.v. predikant/spreekbeurt voor beheerdersmatrix (niet bij eigen invulling).
     *
     * @return 'bevestigd'|'afgewezen'|'uitgevraagd'|null
     */
    private function sprekerStatusVoorMatrix(Dienst $dienst): ?string
    {
        if (! empty($dienst->eigeninvulling) && is_string($dienst->eigeninvulling)) {
            $t = trim($dienst->eigeninvulling);
            if ($t !== '') {
                return null;
            }
        }

        $spreekbeurt = $dienst->spreekbeurten->first();
        if ($spreekbeurt === null) {
            return 'uitgevraagd';
        }

        return match ((int) ($spreekbeurt->bevestigd ?? -1)) {
            1 => 'bevestigd',
            0 => 'afgewezen',
            default => 'uitgevraagd',
        };
    }

    private function weergaveVoorCel(Dienst $dienst, bool $alleenBevestigdeSprekers = false): string
    {
        if (! empty($dienst->eigeninvulling) && is_string($dienst->eigeninvulling)) {
            $t = trim($dienst->eigeninvulling);

            return $t !== '' ? __('api.rooster.custom_content_with', ['text' => Str::limit($t, 120)]) : __('api.rooster.custom_content');
        }
        $spreekbeurt = $dienst->spreekbeurten->first();
        if ($spreekbeurt?->spreker) {
            if ($alleenBevestigdeSprekers && ! $spreekbeurt->isBevestigd()) {
                return '—';
            }

            return trim(implode(' ', array_filter([
                $spreekbeurt->spreker->voornaam,
                $spreekbeurt->spreker->tussenvoegsel,
                $spreekbeurt->spreker->achternaam,
            ])));
        }

        return '—';
    }

    private function sprekerPhotoUrlVoorCel(Dienst $dienst, bool $alleenBevestigdeSprekers = false): ?string
    {
        if (! empty($dienst->eigeninvulling) && is_string($dienst->eigeninvulling)) {
            $t = trim($dienst->eigeninvulling);
            if ($t !== '') {
                return null;
            }
        }

        $spreekbeurt = $dienst->spreekbeurten->first();
        if ($spreekbeurt?->spreker) {
            if ($alleenBevestigdeSprekers && ! $spreekbeurt->isBevestigd()) {
                return null;
            }

            return $spreekbeurt->spreker->photo_url;
        }

        return null;
    }

    private function nullableTrimmedString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function normaliseerTijdVoorMatrix(?string $value): ?string
    {
        $normalized = $this->nullableTrimmedString($value);
        if ($normalized === null) {
            return null;
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $normalized) === 1) {
            return substr($normalized, 0, 5);
        }

        return $normalized;
    }

    private function isPubliekToegestaneMaand(string $maand): bool
    {
        $tz = self::TIMEZONE;
        $nu = Carbon::now($tz);
        $huidige = $nu->format('Y-m');
        $volgende = $nu->copy()->addMonth()->format('Y-m');

        return $maand === $huidige || $maand === $volgende;
    }

    private function isGepubliceerdInPubliekVenster(string $maand): bool
    {
        if (! $this->isPubliekToegestaneMaand($maand)) {
            return false;
        }

        return Publicatie::query()
            ->where('periode', $maand)
            ->whereNull('gemeente_id')
            ->where('gepubliceerd', true)
            ->exists();
    }

    private function isOpenPlek(Dienst $dienst, string $weergave): bool
    {
        if ($weergave !== '—') {
            return false;
        }

        if (! empty($dienst->eigeninvulling) && trim((string) $dienst->eigeninvulling) !== '') {
            return false;
        }

        return true;
    }
}

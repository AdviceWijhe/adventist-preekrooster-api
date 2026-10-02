<?php

declare(strict_types=1);

namespace App\Services\Statistieken;

use App\Models\Bericht;
use App\Models\ChangeLog;
use App\Models\Dienst;
use App\Models\Functie;
use App\Models\Gemeente;
use App\Models\Publicatie;
use App\Models\Spreekbeurt;
use App\Models\User;
use App\Models\UserBeschikbaarheid;
use App\Services\Bericht\BerichtService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StatistiekenService
{
    public const SECTIE_OVERZICHT = 'overzicht';

    public const SECTIE_ROOSTER = 'rooster';

    public const SECTIE_PREDIKANTEN = 'predikanten';

    public const SECTIE_ORGANISATIE = 'organisatie';

    public const SECTIE_COMMUNICATIE = 'communicatie';

    public const SECTIE_REIZEN = 'reizen';

    /**
     * Jaartallen waarin minstens één dienst voorkomt, aflopend gesorteerd.
     *
     * @return list<int>
     */
    public function beschikbareJaren(): array
    {
        $jaarExpr = DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%Y', datum) AS INTEGER)"
            : 'YEAR(datum)';

        return Dienst::query()
            ->selectRaw("{$jaarExpr} as jaar")
            ->groupBy('jaar')
            ->orderByDesc('jaar')
            ->pluck('jaar')
            ->map(fn ($jaar) => (int) $jaar)
            ->all();
    }

    /**
     * @return list<string>
     */
    public function beschikbareSecties(): array
    {
        $secties = [
            self::SECTIE_OVERZICHT,
            self::SECTIE_ROOSTER,
            self::SECTIE_PREDIKANTEN,
            self::SECTIE_ORGANISATIE,
            self::SECTIE_COMMUNICATIE,
        ];

        if (config('statistieken.km_enabled')) {
            $secties[] = self::SECTIE_REIZEN;
        }

        return $secties;
    }

    /**
     * @return array<string, mixed>
     */
    public function voorSectie(string $sectie, StatistiekenFilter $filter): array
    {
        return match ($sectie) {
            self::SECTIE_OVERZICHT => $this->overzicht($filter),
            self::SECTIE_ROOSTER => $this->rooster($filter),
            self::SECTIE_PREDIKANTEN => $this->predikanten($filter),
            self::SECTIE_ORGANISATIE => $this->organisatie($filter),
            self::SECTIE_COMMUNICATIE => $this->communicatie($filter),
            self::SECTIE_REIZEN => $this->reizen($filter),
            default => throw new \InvalidArgumentException("Onbekende sectie: {$sectie}"),
        };
    }

    /**
     * @deprecated Use voorSectie() — behouden voor backward compatibility in tests
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function samenvatting(array $filters): array
    {
        $filter = StatistiekenFilter::fromArray($filters);
        $data = $this->overzicht($filter);

        return [
            'beurtenPerPredikant' => $data['beurtenPerPredikant'],
            'beurtenPerGemeente' => $data['beurtenPerGemeente'],
            'bijzonderheden' => $data['bijzonderheden'],
            'bevestigingspercentage' => $data['kpis']['bevestigingspercentage'],
            'filters' => $filter->toArray(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function naarCsv(array $filters, ?string $sectie = null): string
    {
        $filter = StatistiekenFilter::fromArray($filters);
        $sectie = $sectie ?? self::SECTIE_OVERZICHT;

        if ($sectie === self::SECTIE_REIZEN && ! config('statistieken.km_enabled')) {
            $sectie = self::SECTIE_OVERZICHT;
        }

        $data = $this->voorSectie($sectie, $filter);
        $lines = ["Statistieken — {$sectie}", ''];

        foreach ($data['kpis'] ?? [] as $key => $value) {
            if (is_array($value)) {
                continue;
            }
            $lines[] = $this->csvRij([(string) $key, (string) $value]);
        }

        foreach ($data['tabellen'] ?? [] as $tabel) {
            $lines[] = '';
            $lines[] = $tabel['titel'] ?? 'Tabel';
            if (! empty($tabel['kolommen'])) {
                $lines[] = implode(';', $tabel['kolommen']);
            }
            foreach ($tabel['rijen'] ?? [] as $rij) {
                $lines[] = $this->csvRij(array_map(fn ($v) => (string) $v, $rij));
            }
        }

        if ($sectie === self::SECTIE_OVERZICHT) {
            $lines = array_merge($lines, $this->legacyCsvBlokken($filter));
        }

        return implode("\r\n", $lines)."\r\n";
    }

    /**
     * @return array<string, mixed>
     */
    private function overzicht(StatistiekenFilter $filter): array
    {
        $totaalDiensten = $this->dienstQuery($filter)->count();
        $gekoppeld = $this->dienstQuery($filter)
            ->whereHas('spreekbeurten', fn (Builder $q) => $this->pasSpreekbeurtFilterToe($q, $filter))
            ->count();

        $beurtenQuery = $this->spreekbeurtQuery($filter);
        $totaalBeurten = (clone $beurtenQuery)->count();
        $bevestigd = (clone $beurtenQuery)->where('bevestigd', 1)->count();
        $afgewezen = (clone $beurtenQuery)->where('bevestigd', 0)->count();
        $uitgevraagd = (clone $beurtenQuery)->whereNull('bevestigd')->count();

        $bevestigingspercentage = $totaalBeurten > 0
            ? round(($bevestigd / $totaalBeurten) * 100, 1)
            : 0.0;

        $vulgraad = $totaalDiensten > 0
            ? round(($gekoppeld / $totaalDiensten) * 100, 1)
            : 0.0;

        return [
            'sectie' => self::SECTIE_OVERZICHT,
            'kpis' => [
                'bevestigingspercentage' => $bevestigingspercentage,
                'vulgraad' => $vulgraad,
                'totaalDiensten' => $totaalDiensten,
                'totaalBeurten' => $totaalBeurten,
                'openDiensten' => max(0, $totaalDiensten - $gekoppeld),
            ],
            'series' => [
                'dienstenPerMaand' => $this->dienstenPerMaand($filter),
                'bevestigingsFunnel' => [
                    'labels' => ['Uitgevraagd', 'Afgewezen', 'Bevestigd'],
                    'data' => [$uitgevraagd, $afgewezen, $bevestigd],
                ],
                'vulgraad' => [
                    'labels' => ['Gekoppeld', 'Open'],
                    'data' => [$gekoppeld, max(0, $totaalDiensten - $gekoppeld)],
                ],
            ],
            'beurtenPerPredikant' => $this->beurtenPerPredikant($filter),
            'beurtenPerGemeente' => $this->beurtenPerGemeente($filter),
            'bijzonderheden' => $this->bijzonderheden($filter),
            'filters' => $filter->toArray(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function rooster(StatistiekenFilter $filter): array
    {
        $typeVerdeling = $this->groeperingOpDienstVeld($filter, 'type', [
            'sabbatschool' => 'Sabbatschool',
            'eredienst' => 'Eredienst',
            'speciaal' => 'Speciaal',
        ]);
        $taalVerdeling = $this->groeperingOpDienstVeld($filter, 'taal', [
            'nl' => 'Nederlands',
            'en' => 'Engels',
        ]);
        $wijzeVerdeling = $this->groeperingOpDienstVeld($filter, 'dienstwijze', [
            'fysiek' => 'Fysiek',
            'digitaal' => 'Digitaal',
            'fysiek_digitaal' => 'Fysiek + digitaal',
        ]);

        $afgewezen = $this->spreekbeurtQuery($filter)
            ->where('bevestigd', 0)
            ->with(['spreker:id,voornaam,tussenvoegsel,achternaam', 'dienst.gemeente:id,naam'])
            ->latest('updated_at')
            ->limit(50)
            ->get()
            ->map(fn (Spreekbeurt $s) => [
                'predikant' => $this->userNaam($s->spreker),
                'gemeente' => $s->dienst?->gemeente?->naam ?? '—',
                'datum' => $s->dienst?->datum?->format('Y-m-d') ?? '—',
                'bericht' => $s->bericht ?? '',
            ]);

        $eigeninvulling = $this->dienstQuery($filter)
            ->whereNotNull('eigeninvulling')
            ->where('eigeninvulling', '!=', '')
            ->with('gemeente:id,naam')
            ->orderBy('datum')
            ->limit(50)
            ->get()
            ->map(fn (Dienst $d) => [
                $d->datum->format('Y-m-d'),
                $d->gemeente?->naam ?? '—',
                $d->eigeninvulling ?? '',
            ]);

        return [
            'sectie' => self::SECTIE_ROOSTER,
            'kpis' => [
                'totaalDiensten' => $this->dienstQuery($filter)->count(),
                'bijzondereDiensten' => $this->dienstQuery($filter)->whereNotNull('bijzonderheid_id')->count(),
                'eigeninvulling' => $this->dienstQuery($filter)->whereNotNull('eigeninvulling')->where('eigeninvulling', '!=', '')->count(),
            ],
            'series' => [
                'typeVerdeling' => $typeVerdeling,
                'taalVerdeling' => $taalVerdeling,
                'dienstwijzeVerdeling' => $wijzeVerdeling,
                'bijzonderheden' => $this->bijzonderhedenSeries($filter),
            ],
            'tabellen' => [
                [
                    'titel' => 'Afgewezen beurten',
                    'kolommen' => ['Predikant', 'Gemeente', 'Datum', 'Bericht'],
                    'rijen' => $afgewezen->map(fn ($r) => [$r['predikant'], $r['gemeente'], $r['datum'], $r['bericht']])->all(),
                ],
                [
                    'titel' => 'Eigen invulling',
                    'kolommen' => ['Datum', 'Gemeente', 'Omschrijving'],
                    'rijen' => $eigeninvulling->all(),
                ],
            ],
            'bijzonderheden' => $this->bijzonderheden($filter),
            'filters' => $filter->toArray(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function predikanten(StatistiekenFilter $filter): array
    {
        $predikantIds = $this->actieveSprekerIds($filter);

        $zonderBeurten = User::query()
            ->whereIn('id', $predikantIds)
            ->whereDoesntHave('spreekbeurten', fn (Builder $q) => $this->pasSpreekbeurtFilterToe($q, $filter))
            ->get(['id', 'voornaam', 'tussenvoegsel', 'achternaam', 'spreekniveau', 'landelijk_actief'])
            ->map(fn (User $u) => [
                $this->userNaam($u),
                $u->spreekniveau ?? '—',
                $u->landelijk_actief ? 'Ja' : 'Nee',
            ]);

        $maandExpr = $this->sqlMaandExpr('diensten.datum');
        $werkbelasting = $this->spreekbeurtQuery($filter)
            ->selectRaw("spreker_id, {$maandExpr} as maand, COUNT(*) as aantal")
            ->join('diensten', 'diensten.id', '=', 'spreekbeurten.dienst_id')
            ->groupBy('spreker_id', DB::raw($maandExpr))
            ->with(['spreker:id,voornaam,tussenvoegsel,achternaam'])
            ->get();

        $werkbelastingRijen = $werkbelasting->map(fn ($r) => [
            $this->userNaam($r->spreker),
            $this->maandNaam((int) $r->maand),
            (string) $r->aantal,
        ]);

        $spreekniveau = $this->spreekbeurtQuery($filter)
            ->join('users', 'users.id', '=', 'spreekbeurten.spreker_id')
            ->selectRaw('users.spreekniveau, COUNT(*) as aantal')
            ->groupBy('users.spreekniveau')
            ->get()
            ->map(fn ($r) => [
                'key' => $r->spreekniveau ?? 'onbekend',
                'label' => config('identiteit.spreekniveaus.'.$r->spreekniveau, $r->spreekniveau ?? 'Onbekend'),
                'aantal' => (int) $r->aantal,
            ]);

        $functieCounts = $this->beurtenPerFunctie($filter);

        $conflicten = $this->beschikbaarheidsConflicten($filter);

        $totaalPredikanten = count($predikantIds);
        $icalCount = User::query()->whereIn('id', $predikantIds)->whereNotNull('agenda_token')->count();
        $tweeFaCount = User::query()->whereIn('id', $predikantIds)->where('two_factor_enabled', true)->count();

        $onbeschikbaarDagen = UserBeschikbaarheid::query()
            ->whereIn('user_id', $predikantIds)
            ->whereYear('datum_van', '<=', $filter->jaar)
            ->whereYear('datum_tot', '>=', $filter->jaar)
            ->get()
            ->sum(fn (UserBeschikbaarheid $b) => $b->datum_van->diffInDays($b->datum_tot) + 1);

        $reactietijd = $this->gemiddeldeReactietijdDagen($filter);

        return [
            'sectie' => self::SECTIE_PREDIKANTEN,
            'kpis' => [
                'actieveSprekers' => $totaalPredikanten,
                'icalAdoptie' => $totaalPredikanten > 0 ? round(($icalCount / $totaalPredikanten) * 100, 1) : 0.0,
                'tweeFaAdoptie' => $totaalPredikanten > 0 ? round(($tweeFaCount / $totaalPredikanten) * 100, 1) : 0.0,
                'onbeschikbaarDagen' => (int) $onbeschikbaarDagen,
                'gemiddeldeReactietijdDagen' => $reactietijd,
                'conflictCount' => count($conflicten),
            ],
            'series' => [
                'spreekniveau' => [
                    'labels' => $spreekniveau->pluck('label')->all(),
                    'data' => $spreekniveau->pluck('aantal')->all(),
                ],
                'functies' => [
                    'labels' => $functieCounts->pluck('label')->all(),
                    'data' => $functieCounts->pluck('aantal')->all(),
                ],
            ],
            'tabellen' => [
                [
                    'titel' => 'Werkbelasting per maand',
                    'kolommen' => ['Predikant', 'Maand', 'Beurten'],
                    'rijen' => $werkbelastingRijen->all(),
                ],
                [
                    'titel' => 'Predikanten zonder beurten',
                    'kolommen' => ['Naam', 'Spreekniveau', 'Landelijk actief'],
                    'rijen' => $zonderBeurten->all(),
                ],
                [
                    'titel' => 'Conflicten met beschikbaarheid',
                    'kolommen' => ['Predikant', 'Datum', 'Opmerking'],
                    'rijen' => $conflicten,
                ],
            ],
            'beurtenPerPredikant' => $this->beurtenPerPredikant($filter),
            'filters' => $filter->toArray(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function organisatie(StatistiekenFilter $filter): array
    {
        $districtData = Gemeente::query()
            ->when($filter->districtId !== null, fn (Builder $q) => $q->where('district_id', $filter->districtId))
            ->when($filter->gemeenteId !== null, fn (Builder $q) => $q->where('id', $filter->gemeenteId))
            ->with('district:id,naam')
            ->withCount(['diensten as diensten_count' => fn (Builder $q) => $filter->applyToDienst($q)])
            ->get()
            ->groupBy(fn (Gemeente $g) => $g->district?->naam ?? 'Geen district')
            ->map(fn (Collection $groep, string $naam) => [
                'label' => $naam,
                'aantal' => $groep->sum('diensten_count'),
            ])
            ->values();

        $zonderPredikant = Gemeente::query()
            ->where('active', true)
            ->whereNull('predikant_id')
            ->orderBy('naam')
            ->pluck('naam')
            ->map(fn (string $naam) => [$naam]);

        $zonderContact = Gemeente::query()
            ->where('active', true)
            ->whereNull('contactpersoon_id')
            ->orderBy('naam')
            ->pluck('naam')
            ->map(fn (string $naam) => [$naam]);

        $churchPlant = $this->dienstQuery($filter)
            ->whereHas('gemeente', fn (Builder $q) => $q->where('church_plant', true))
            ->count();
        $regulier = $this->dienstQuery($filter)
            ->whereHas('gemeente', fn (Builder $q) => $q->where('church_plant', false))
            ->count();

        $publicaties = Publicatie::query()
            ->where('gepubliceerd', true)
            ->where('periode', 'like', $filter->jaar.'-%')
            ->with(['gepubliceerdDoor:id,voornaam,tussenvoegsel,achternaam'])
            ->orderBy('periode')
            ->get()
            ->map(fn (Publicatie $p) => [
                $p->periode,
                $p->gepubliceerd_op?->format('Y-m-d H:i') ?? '—',
                $p->gepubliceerdDoor ? $this->userNaam($p->gepubliceerdDoor) : '—',
            ]);

        $gepubliceerdeMaanden = Publicatie::query()
            ->where('gepubliceerd', true)
            ->where('periode', 'like', $filter->jaar.'-%')
            ->pluck('periode')
            ->all();

        $periodeExpr = $this->sqlJaarMaandExpr('datum');
        $maandenMetDiensten = $this->dienstQuery($filter)
            ->selectRaw("{$periodeExpr} as periode")
            ->groupBy('periode')
            ->pluck('periode')
            ->all();

        $zonderPublicatie = array_values(array_diff($maandenMetDiensten, $gepubliceerdeMaanden));

        return [
            'sectie' => self::SECTIE_ORGANISATIE,
            'kpis' => [
                'actieveGemeentes' => Gemeente::query()->where('active', true)->count(),
                'churchPlantDiensten' => $churchPlant,
                'reguliereDiensten' => $regulier,
                'publicaties' => $publicaties->count(),
                'maandenZonderPublicatie' => count($zonderPublicatie),
            ],
            'series' => [
                'districten' => [
                    'labels' => $districtData->pluck('label')->all(),
                    'data' => $districtData->pluck('aantal')->all(),
                ],
                'churchPlant' => [
                    'labels' => ['Church plant', 'Regulier'],
                    'data' => [$churchPlant, $regulier],
                ],
            ],
            'tabellen' => [
                [
                    'titel' => 'Publicaties',
                    'kolommen' => ['Periode', 'Gepubliceerd op', 'Door'],
                    'rijen' => $publicaties->all(),
                ],
                [
                    'titel' => 'Maanden zonder publicatie',
                    'kolommen' => ['Periode'],
                    'rijen' => array_map(fn (string $p) => [$p], $zonderPublicatie),
                ],
                [
                    'titel' => 'Gemeentes zonder predikant',
                    'kolommen' => ['Gemeente'],
                    'rijen' => $zonderPredikant->all(),
                ],
                [
                    'titel' => 'Gemeentes zonder contactpersoon',
                    'kolommen' => ['Gemeente'],
                    'rijen' => $zonderContact->all(),
                ],
            ],
            'beurtenPerGemeente' => $this->beurtenPerGemeente($filter),
            'filters' => $filter->toArray(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function communicatie(StatistiekenFilter $filter): array
    {
        $berichtenQuery = Bericht::query()
            ->whereNotNull('gepubliceerd_op')
            ->whereYear('gepubliceerd_op', $filter->jaar);

        if ($filter->van !== null) {
            $berichtenQuery->whereDate('gepubliceerd_op', '>=', $filter->van);
        }
        if ($filter->tot !== null) {
            $berichtenQuery->whereDate('gepubliceerd_op', '<=', $filter->tot);
        }

        $berichten = (clone $berichtenQuery)->withCount('gelezenDoor')->get();

        $berichtRijen = $berichten->map(function (Bericht $b) {
            $doelgroepGrootte = app(BerichtService::class)
                ->doelgroepGebruikers($b)->count();
            $gelezen = $b->gelezen_door_count;
            $ratio = $doelgroepGrootte > 0 ? round(($gelezen / $doelgroepGrootte) * 100, 1) : 0.0;

            return [
                $b->titel,
                $b->doelgroep,
                $b->kanaal,
                $b->gepubliceerd_op?->format('Y-m-d') ?? '—',
                "{$gelezen}/{$doelgroepGrootte} ({$ratio}%)",
            ];
        });

        $berichtMaandExpr = $this->sqlMaandExpr('gepubliceerd_op');
        $perMaand = (clone $berichtenQuery)
            ->selectRaw("{$berichtMaandExpr} as maand, COUNT(*) as aantal")
            ->groupBy(DB::raw($berichtMaandExpr))
            ->orderBy('maand')
            ->get();

        $weekExpr = $this->sqlWeekExpr('created_at');
        $changelog = ChangeLog::query()
            ->whereYear('created_at', $filter->jaar)
            ->when($filter->van !== null, fn (Builder $q) => $q->whereDate('created_at', '>=', $filter->van))
            ->when($filter->tot !== null, fn (Builder $q) => $q->whereDate('created_at', '<=', $filter->tot))
            ->selectRaw("{$weekExpr} as week, COUNT(*) as aantal")
            ->groupBy(DB::raw($weekExpr))
            ->orderBy('week')
            ->get();

        $koppelen = ChangeLog::query()
            ->where('actie', 'Spreker gekoppeld')
            ->whereYear('created_at', $filter->jaar)
            ->count();
        $verwijderen = ChangeLog::query()
            ->where('actie', 'Spreker verwijderd')
            ->whereYear('created_at', $filter->jaar)
            ->count();

        $gemLeesratio = $berichtRijen->isEmpty() ? 0.0 : round(
            $berichten->avg(function (Bericht $b) {
                $doel = app(BerichtService::class)->doelgroepGebruikers($b)->count();

                return $doel > 0 ? ($b->gelezen_door_count / $doel) * 100 : 0;
            }) ?? 0,
            1
        );

        return [
            'sectie' => self::SECTIE_COMMUNICATIE,
            'kpis' => [
                'berichten' => $berichten->count(),
                'gemiddeldeLeesratio' => $gemLeesratio,
                'changelogEntries' => ChangeLog::query()
                    ->whereYear('created_at', $filter->jaar)
                    ->when($filter->van, fn (Builder $q) => $q->whereDate('created_at', '>=', $filter->van))
                    ->when($filter->tot, fn (Builder $q) => $q->whereDate('created_at', '<=', $filter->tot))
                    ->count(),
                'koppelingen' => $koppelen,
                'verwijderingen' => $verwijderen,
            ],
            'series' => [
                'berichtenPerMaand' => [
                    'labels' => $perMaand->map(fn ($r) => $this->maandNaam((int) $r->maand))->all(),
                    'data' => $perMaand->pluck('aantal')->map(fn ($v) => (int) $v)->all(),
                ],
                'changelogPerWeek' => [
                    'labels' => $changelog->map(fn ($r) => 'Week '.(int) $r->week)->all(),
                    'data' => $changelog->pluck('aantal')->map(fn ($v) => (int) $v)->all(),
                ],
                'koppelingTrend' => [
                    'labels' => ['Gekoppeld', 'Verwijderd'],
                    'data' => [$koppelen, $verwijderen],
                ],
            ],
            'tabellen' => [
                [
                    'titel' => 'Berichten',
                    'kolommen' => ['Titel', 'Doelgroep', 'Kanaal', 'Gepubliceerd', 'Leesratio'],
                    'rijen' => $berichtRijen->all(),
                ],
            ],
            'filters' => $filter->toArray(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reizen(StatistiekenFilter $filter): array
    {
        if (! config('statistieken.km_enabled')) {
            return [
                'sectie' => self::SECTIE_REIZEN,
                'kpis' => [],
                'series' => [],
                'tabellen' => [],
                'filters' => $filter->toArray(),
                'disabled' => true,
            ];
        }

        $query = $this->spreekbeurtQuery($filter);
        $metKm = (clone $query)->whereNotNull('kilometers')->where('kilometers', '>', 0);
        $totaalKm = (int) (clone $metKm)->sum('kilometers');
        $beurtenMetKm = (clone $metKm)->count();
        $totaalBeurten = (clone $query)->count();

        $perPredikant = (clone $metKm)
            ->selectRaw('spreker_id, SUM(kilometers) as totaal_km, COUNT(*) as beurten, AVG(kilometers) as gem_km')
            ->groupBy('spreker_id')
            ->orderByDesc('totaal_km')
            ->with(['spreker:id,voornaam,tussenvoegsel,achternaam'])
            ->get();

        $perGemeente = (clone $metKm)
            ->join('diensten', 'diensten.id', '=', 'spreekbeurten.dienst_id')
            ->join('gemeentes', 'gemeentes.id', '=', 'diensten.gemeente_id')
            ->selectRaw('gemeentes.naam as gemeente_naam, SUM(spreekbeurten.kilometers) as totaal_km')
            ->groupBy('gemeentes.id', 'gemeentes.naam')
            ->orderByDesc('totaal_km')
            ->get();

        return [
            'sectie' => self::SECTIE_REIZEN,
            'kpis' => [
                'totaalKm' => $totaalKm,
                'beurtenMetKm' => $beurtenMetKm,
                'kmVulgraad' => $totaalBeurten > 0 ? round(($beurtenMetKm / $totaalBeurten) * 100, 1) : 0.0,
                'gemiddeldeKm' => $beurtenMetKm > 0 ? round($totaalKm / $beurtenMetKm, 1) : 0.0,
            ],
            'series' => [
                'kmPerPredikant' => [
                    'labels' => $perPredikant->map(fn ($r) => $this->userNaam($r->spreker))->all(),
                    'data' => $perPredikant->pluck('totaal_km')->map(fn ($v) => (int) $v)->all(),
                ],
                'kmVulgraad' => [
                    'labels' => ['Met km', 'Zonder km'],
                    'data' => [$beurtenMetKm, max(0, $totaalBeurten - $beurtenMetKm)],
                ],
            ],
            'tabellen' => [
                [
                    'titel' => 'Kilometers per predikant',
                    'kolommen' => ['Predikant', 'Totaal km', 'Beurten', 'Gem. km'],
                    'rijen' => $perPredikant->map(fn ($r) => [
                        $this->userNaam($r->spreker),
                        (string) $r->totaal_km,
                        (string) $r->beurten,
                        (string) round((float) $r->gem_km, 1),
                    ])->all(),
                ],
                [
                    'titel' => 'Kilometers per gemeente',
                    'kolommen' => ['Gemeente', 'Totaal km'],
                    'rijen' => $perGemeente->map(fn ($r) => [
                        $r->gemeente_naam ?? '—',
                        (string) $r->totaal_km,
                    ])->all(),
                ],
            ],
            'filters' => $filter->toArray(),
        ];
    }

    private function dienstQuery(StatistiekenFilter $filter): Builder
    {
        $query = Dienst::query();
        $filter->applyToDienst($query);

        return $query;
    }

    private function spreekbeurtQuery(StatistiekenFilter $filter): Builder
    {
        $query = Spreekbeurt::query();
        $filter->applyToSpreekbeurt($query);

        return $query;
    }

    private function pasSpreekbeurtFilterToe(Builder $query, StatistiekenFilter $filter): void
    {
        if ($filter->sprekerId !== null) {
            $query->where('spreker_id', $filter->sprekerId);
        }
    }

    private function beurtenPerPredikant(StatistiekenFilter $filter): Collection
    {
        return $this->spreekbeurtQuery($filter)
            ->with(['spreker' => fn ($q) => $q->select('id', 'voornaam', 'tussenvoegsel', 'achternaam')])
            ->selectRaw('spreker_id, COUNT(*) as aantal, SUM(CASE WHEN bevestigd = 1 THEN 1 ELSE 0 END) as bevestigd')
            ->groupBy('spreker_id')
            ->orderByDesc('aantal')
            ->get();
    }

    private function beurtenPerGemeente(StatistiekenFilter $filter): Collection
    {
        return Dienst::query()
            ->when($filter->gemeenteId !== null, fn (Builder $q) => $q->where('diensten.gemeente_id', $filter->gemeenteId))
            ->when($filter->districtId !== null, fn (Builder $q) => $q->whereHas(
                'gemeente',
                fn (Builder $g) => $g->where('district_id', $filter->districtId)
            ))
            ->where(function (Builder $q) use ($filter): void {
                $filter->applyToDienst($q);
            })
            ->with(['gemeente' => fn ($q) => $q->select('id', 'naam')])
            ->selectRaw('diensten.gemeente_id, COUNT(diensten.id) as aantal_diensten, COUNT(spreekbeurten.id) as gekoppeld')
            ->leftJoin('spreekbeurten', function ($join) use ($filter): void {
                $join->on('diensten.id', '=', 'spreekbeurten.dienst_id');
                if ($filter->sprekerId !== null) {
                    $join->where('spreekbeurten.spreker_id', '=', $filter->sprekerId);
                }
            })
            ->groupBy('diensten.gemeente_id')
            ->orderByDesc('aantal_diensten')
            ->get();
    }

    private function bijzonderheden(StatistiekenFilter $filter): Collection
    {
        return Dienst::query()
            ->when($filter->gemeenteId !== null, fn (Builder $q) => $q->where('gemeente_id', $filter->gemeenteId))
            ->when($filter->districtId !== null, fn (Builder $q) => $q->whereHas(
                'gemeente',
                fn (Builder $g) => $g->where('district_id', $filter->districtId)
            ))
            ->where(function (Builder $q) use ($filter): void {
                $filter->applyToDienst($q);
            })
            ->whereNotNull('bijzonderheid_id')
            ->with(['bijzonderheid' => fn ($q) => $q->select('id', 'naam')])
            ->selectRaw('bijzonderheid_id, COUNT(*) as aantal')
            ->groupBy('bijzonderheid_id')
            ->orderByDesc('aantal')
            ->get();
    }

    /**
     * @return array{labels: list<string>, data: list<int>}
     */
    private function bijzonderhedenSeries(StatistiekenFilter $filter): array
    {
        $rows = $this->bijzonderheden($filter);

        return [
            'labels' => $rows->map(fn ($r) => $r->bijzonderheid?->naam ?? '—')->all(),
            'data' => $rows->pluck('aantal')->map(fn ($v) => (int) $v)->all(),
        ];
    }

    /**
     * @param  array<string, string>  $labels
     * @return array{labels: list<string>, data: list<int>}
     */
    private function groeperingOpDienstVeld(StatistiekenFilter $filter, string $veld, array $labels): array
    {
        $counts = $this->dienstQuery($filter)
            ->selectRaw("{$veld}, COUNT(*) as aantal")
            ->groupBy($veld)
            ->pluck('aantal', $veld);

        $resultLabels = [];
        $resultData = [];

        foreach ($labels as $key => $label) {
            $resultLabels[] = $label;
            $resultData[] = (int) ($counts[$key] ?? 0);
        }

        return ['labels' => $resultLabels, 'data' => $resultData];
    }

    /**
     * @return array{labels: list<string>, data: list<int>}
     */
    private function dienstenPerMaand(StatistiekenFilter $filter): array
    {
        $maandExpr = $this->sqlMaandExpr('datum');
        $rows = $this->dienstQuery($filter)
            ->selectRaw("{$maandExpr} as maand, COUNT(*) as aantal")
            ->groupBy(DB::raw($maandExpr))
            ->orderBy('maand')
            ->get();

        $labels = [];
        $data = [];

        for ($m = 1; $m <= 12; $m++) {
            $labels[] = $this->maandNaam($m);
            $row = $rows->first(fn ($r) => (int) $r->maand === $m);
            $data[] = (int) ($row?->aantal ?? 0);
        }

        return ['labels' => $labels, 'data' => $data];
    }

    /**
     * @return list<int>
     */
    private function actieveSprekerIds(StatistiekenFilter $filter): array
    {
        $ids = $this->spreekbeurtQuery($filter)->distinct()->pluck('spreker_id')->all();

        $pool = User::query()
            ->where('active', true)
            ->where(function (Builder $q): void {
                $q->whereHas('roles', fn (Builder $r) => $r->where('slug', 'predikant'))
                    ->orWhereHas('functies', fn (Builder $f) => $f->whereIn('slug', [
                        'predikant', 'spreker',
                    ]));
            })
            ->pluck('id')
            ->all();

        return array_values(array_unique(array_merge($ids, $pool)));
    }

    /**
     * @return Collection<int, array{label: string, aantal: int}>
     */
    private function beurtenPerFunctie(StatistiekenFilter $filter): Collection
    {
        $functies = Functie::query()->whereIn('slug', ['predikant', 'spreker', 'contactpersoon'])->get();

        return $functies->map(function (Functie $functie) use ($filter) {
            $aantal = $this->spreekbeurtQuery($filter)
                ->whereHas('spreker.functies', fn (Builder $q) => $q->where('functies.id', $functie->id))
                ->count();

            return ['label' => $functie->naam, 'aantal' => $aantal];
        })->filter(fn ($r) => $r['aantal'] > 0)->values();
    }

    /**
     * @return list<list<string>>
     */
    private function beschikbaarheidsConflicten(StatistiekenFilter $filter): array
    {
        $beurten = $this->spreekbeurtQuery($filter)
            ->with(['spreker:id,voornaam,tussenvoegsel,achternaam', 'dienst:id,datum'])
            ->get();

        $conflicten = [];

        foreach ($beurten as $beurt) {
            if (! $beurt->spreker || ! $beurt->dienst?->datum) {
                continue;
            }

            $datum = $beurt->dienst->datum->format('Y-m-d');
            $periodes = UserBeschikbaarheid::query()
                ->where('user_id', $beurt->spreker_id)
                ->whereDate('datum_van', '<=', $datum)
                ->whereDate('datum_tot', '>=', $datum)
                ->get();

            foreach ($periodes as $periode) {
                $conflicten[] = [
                    $this->userNaam($beurt->spreker),
                    $datum,
                    $periode->opmerking ?? '—',
                ];
            }
        }

        return $conflicten;
    }

    private function gemiddeldeReactietijdDagen(StatistiekenFilter $filter): ?float
    {
        $beurten = $this->spreekbeurtQuery($filter)
            ->whereNotNull('bevestigd')
            ->get(['created_at', 'updated_at']);

        if ($beurten->isEmpty()) {
            return null;
        }

        $totaalDagen = $beurten->sum(fn (Spreekbeurt $s) => $s->created_at->diffInDays($s->updated_at));

        return round($totaalDagen / $beurten->count(), 1);
    }

    private function userNaam(?User $user): string
    {
        if (! $user) {
            return '—';
        }

        return $user->volledigeNaam() ?: '—';
    }

    private function maandNaam(int $maand): string
    {
        $namen = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mrt', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
            7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Dec',
        ];

        return $namen[$maand] ?? (string) $maand;
    }

    private function sqlMaandExpr(string $column): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return "CAST(strftime('%m', {$column}) AS INTEGER)";
        }

        return "MONTH({$column})";
    }

    private function sqlJaarMaandExpr(string $column): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return "strftime('%Y-%m', {$column})";
        }

        return "DATE_FORMAT({$column}, '%Y-%m')";
    }

    private function sqlWeekExpr(string $column): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return "CAST(strftime('%W', {$column}) AS INTEGER)";
        }

        return "WEEK({$column}, 3)";
    }

    /**
     * @return list<string>
     */
    private function legacyCsvBlokken(StatistiekenFilter $filter): array
    {
        $data = $this->overzicht($filter);
        $lines = [];

        $lines[] = '';
        $lines[] = 'Beurten per predikant';
        $lines[] = 'Naam;Aantal;Bevestigd';
        foreach ($data['beurtenPerPredikant'] as $rij) {
            $lines[] = $this->csvRij([
                $this->userNaam($rij->spreker),
                (string) $rij->aantal,
                (string) $rij->bevestigd,
            ]);
        }

        $lines[] = '';
        $lines[] = 'Diensten per gemeente';
        $lines[] = 'Gemeente;Diensten;Gekoppelde beurten';
        foreach ($data['beurtenPerGemeente'] as $rij) {
            $lines[] = $this->csvRij([
                $rij->gemeente->naam ?? '—',
                (string) $rij->aantal_diensten,
                (string) $rij->gekoppeld,
            ]);
        }

        $lines[] = '';
        $lines[] = 'Bijzondere diensten';
        $lines[] = 'Bijzonderheid;Aantal';
        foreach ($data['bijzonderheden'] as $rij) {
            $lines[] = $this->csvRij([
                $rij->bijzonderheid->naam ?? '—',
                (string) $rij->aantal,
            ]);
        }

        return $lines;
    }

    /**
     * @param  list<string|null>  $velden
     */
    private function csvRij(array $velden): string
    {
        return implode(';', array_map(fn (?string $veld) => $this->escapeCsv((string) ($veld ?? '')), $velden));
    }

    private function escapeCsv(string $value): string
    {
        if (str_contains($value, ';') || str_contains($value, '"') || str_contains($value, "\n")) {
            return '"'.str_replace('"', '""', $value).'"';
        }

        return $value;
    }
}

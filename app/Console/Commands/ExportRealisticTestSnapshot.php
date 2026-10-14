<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

class ExportRealisticTestSnapshot extends Command
{
    private const DISTRICT_ZERO_ID = 3;

    protected $signature = 'testdata:export-snapshot
        {--path= : Relatief pad onder database/ (standaard: data/realistic-test-snapshot.json)}';

    protected $description = 'Exporteert geanonimiseerde testdata uit OLD_DB_* naar een JSON-snapshot in de repo.';

    public function handle(): int
    {
        $this->configureOldConnection();

        try {
            $oud = DB::connection('oud');
            $oud->getPdo();
        } catch (Throwable $e) {
            $this->error('Kan OLD_DB niet bereiken: '.$e->getMessage());
            $this->line('Controleer OLD_DB_HOST, OLD_DB_PORT, OLD_DB_DATABASE, OLD_DB_USERNAME, OLD_DB_PASSWORD.');

            return self::FAILURE;
        }

        $anchor = Carbon::today();
        $from = $anchor->copy()->subMonths(2)->toDateString();
        $to = $anchor->copy()->addMonths(2)->toDateString();

        $this->info("Venster: {$from} … {$to} (anchor {$anchor->toDateString()})");

        $districts = $this->exportDistricts($oud);
        $gemeentes = $this->exportGemeentes($oud);
        $bijzonderheden = $this->exportBijzonderheden($oud);

        $preekbeurten = $oud->table('preekbeurten')
            ->whereBetween('date', [$from, $to])
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $preekbeurtIds = $preekbeurten->pluck('id')->all();

        $sprekers = collect();
        if ($preekbeurtIds !== []) {
            $sprekers = $oud->table('preekbeurten_sprekers')
                ->whereIn('preekbeurt_id', $preekbeurtIds)
                ->orderBy('id')
                ->get();
        }

        $userIds = collect()
            ->merge($sprekers->pluck('user_id'))
            ->merge($sprekers->pluck('entered_by_user_id'))
            ->merge($gemeentes->pluck('predikant_id'))
            ->merge($gemeentes->pluck('contactpersoon_id'))
            ->filter(fn ($id): bool => (int) $id > 0)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $users = $this->exportUsers($oud, $userIds);

        $diensten = $preekbeurten->map(function (object $p): array {
            $kind = $p->kind;
            $type = ($kind === null || $kind === '' || (int) $kind === 0)
                ? 'sabbatschool'
                : 'speciaal';

            return [
                'old_id' => (int) $p->id,
                'datum' => (string) $p->date,
                'gemeente_id' => (int) $p->gemeente,
                'type' => $type,
                'eigeninvulling' => ! empty($p->eigeninvulling)
                    ? mb_substr((string) $p->eigeninvulling, 0, 255)
                    : null,
                'taal' => $this->mapTaal($p->language ?? null),
            ];
        })->values()->all();

        $spreekbeurten = $sprekers->map(function (object $s): array {
            $confirmed = $s->confirmed;

            return [
                'old_preekbeurt_id' => (int) $s->preekbeurt_id,
                'spreker_id' => (int) $s->user_id,
                'bevestigd' => $confirmed === null ? null : (int) $confirmed,
                'kilometers' => $s->kilometers !== null ? (int) $s->kilometers : null,
                'ingevoerd_door' => ! empty($s->entered_by_user_id) ? (int) $s->entered_by_user_id : null,
            ];
        })->values()->all();

        $snapshot = [
            'meta' => [
                'exported_at' => now()->toIso8601String(),
                'anchor_date' => $anchor->toDateString(),
                'window_from' => $from,
                'window_to' => $to,
                'source' => env('OLD_DB_DATABASE'),
            ],
            'districts' => $districts->values()->all(),
            'gemeentes' => $gemeentes->values()->all(),
            'bijzonderheden' => $bijzonderheden->values()->all(),
            'users' => $users->values()->all(),
            'diensten' => $diensten,
            'spreekbeurten' => $spreekbeurten,
        ];

        $relative = $this->option('path') ?: 'data/realistic-test-snapshot.json';
        $path = database_path($relative);
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n");

        $this->info('Snapshot geschreven: '.$path);
        $this->table(
            ['Entiteit', 'Aantal'],
            [
                ['districts', count($snapshot['districts'])],
                ['gemeentes', count($snapshot['gemeentes'])],
                ['bijzonderheden', count($snapshot['bijzonderheden'])],
                ['users', count($snapshot['users'])],
                ['diensten', count($snapshot['diensten'])],
                ['spreekbeurten', count($snapshot['spreekbeurten'])],
            ]
        );

        return self::SUCCESS;
    }

    private function configureOldConnection(): void
    {
        config(['database.connections.oud' => [
            'driver' => 'mysql',
            'host' => env('OLD_DB_HOST', '127.0.0.1'),
            'database' => env('OLD_DB_DATABASE', 'advice0000_preekrooster'),
            'username' => env('OLD_DB_USERNAME', 'root'),
            'password' => env('OLD_DB_PASSWORD', ''),
            'port' => env('OLD_DB_PORT', env('DB_PORT', 3306)),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function exportDistricts(mixed $oud): Collection
    {
        return $oud->table('districts')->orderBy('volgorde')->get()->map(function (object $d): array {
            $id = (int) $d->id;
            if ($id === 0) {
                $id = self::DISTRICT_ZERO_ID;
            }

            return [
                'id' => $id,
                'naam' => (string) ($d->naam ?? 'Onbekend'),
                'visible' => true,
                'volgorde' => (int) ($d->volgorde ?? 0),
            ];
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function exportGemeentes(mixed $oud): Collection
    {
        return $oud->table('gemeentes')->orderBy('volgorde')->orderBy('id')->get()->map(function (object $g): array {
            $districtRaw = $g->district;
            $districtId = null;
            if ($districtRaw !== null && $districtRaw !== '') {
                $districtId = (int) $districtRaw === 0
                    ? self::DISTRICT_ZERO_ID
                    : (int) $districtRaw;
            }

            return [
                'id' => (int) $g->id,
                'naam' => (string) ($g->gemeente ?? 'Onbekend'),
                'naam_kort' => $g->gemeente_kort ?? null,
                'adres' => $g->adres ?? null,
                'plaats' => $g->plaats ?? null,
                'postcode' => $g->postcode ?? null,
                'district_id' => $districtId,
                'predikant_id' => ! empty($g->predikant) ? (int) $g->predikant : null,
                'contactpersoon_id' => ! empty($g->contactpersoon) ? (int) $g->contactpersoon : null,
                'begintijd_ochtend' => str_replace('.', ':', (string) ($g->begintijd_ss ?? '10:00')),
                'begintijd_avond' => str_replace('.', ':', (string) ($g->begintijd_ed ?? '18:30')),
                'kerk' => $g->kerk ?? null,
                'website_url' => $g->website_url ?? null,
                'livestream_url' => $g->livestream_url ?? null,
                'taal' => $this->mapTaal($g->taal ?? null),
                'active' => (bool) ($g->active ?? true),
                'church_plant' => (bool) ($g->church_plant ?? false),
                'volgorde' => (int) ($g->volgorde ?? 0),
            ];
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function exportBijzonderheden(mixed $oud): Collection
    {
        if (! $oud->getSchemaBuilder()->hasTable('bijzonderheden')) {
            return collect();
        }

        return $oud->table('bijzonderheden')->orderBy('id')->get()->map(function (object $b): array {
            return [
                'id' => (int) $b->id,
                'naam' => (string) ($b->naam ?? 'Onbekend'),
                'omschrijving' => $b->kortnaam ?? null,
            ];
        });
    }

    /**
     * @param  Collection<int, int>  $userIds
     * @return Collection<int, array<string, mixed>>
     */
    private function exportUsers(mixed $oud, Collection $userIds): Collection
    {
        if ($userIds->isEmpty()) {
            return collect();
        }

        $rows = $oud->table('users')->whereIn('id', $userIds->all())->get()->keyBy('id');

        return $userIds->map(function (int $id) use ($rows): ?array {
            $u = $rows->get($id);
            if (! $u) {
                return null;
            }

            fake()->seed(20260424 + $id);
            $voornaam = fake()->firstName();
            $achternaam = fake()->lastName();
            $geslacht = match ((int) ($u->geslacht ?? 0)) {
                1 => 'm',
                2 => 'v',
                default => 'o',
            };

            $gemeenteId = ! empty($u->gemeente) ? (int) $u->gemeente : null;

            return [
                'id' => $id,
                'voornaam' => $voornaam,
                'tussenvoegsel' => null,
                'achternaam' => $achternaam,
                'initialen' => strtoupper(mb_substr($voornaam, 0, 1).mb_substr($achternaam, 0, 1)),
                'geslacht' => $geslacht,
                'email' => "user_{$id}@example.test",
                'taal' => in_array($u->taal ?? null, ['nl', 'en'], true) ? $u->taal : 'nl',
                'gemeente_id' => $gemeenteId,
                'active' => (bool) ($u->active ?? true),
                'is_tester' => ! empty($u->tester),
            ];
        })->filter()->values();
    }

    private function mapTaal(?string $taal): string
    {
        $taal = strtolower(trim((string) $taal));

        return match ($taal) {
            'dutch', 'nl', 'nederlands' => 'nl',
            'english', 'en', 'engels' => 'en',
            'german', 'de', 'duits' => 'de',
            default => $taal !== '' ? mb_substr($taal, 0, 10) : 'nl',
        };
    }
}

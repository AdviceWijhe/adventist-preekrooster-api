<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class AnalyseerOudeGebruikers extends Command
{
    protected $signature = 'migratie:analyse-gebruikers
        {--maanden= : Drempel in maanden voor "inactief" (standaard MIGRATIE_INACTIEF_MAANDEN of 12)}
        {--limit=20 : Aantal voorbeeldgebruikers dat getoond wordt}';

    protected $description = 'Informatief rapport: welke oude gebruikers zijn lang niet als spreker actief geweest (read-only).';

    public function handle(): int
    {
        $maanden = (int) ($this->option('maanden') ?: env('MIGRATIE_INACTIEF_MAANDEN', 12));
        $limit = (int) $this->option('limit');
        $drempel = Carbon::today()->subMonths($maanden);

        $this->configureOldConnection();

        try {
            $oud = DB::connection('oud');
            $schema = $oud->getSchemaBuilder();

            if (! $schema->hasTable('users')) {
                $this->error("Geen 'users'-tabel gevonden in de oude database.");

                return self::FAILURE;
            }

            $totaal = (int) $oud->table('users')->count();

            $this->info("Oude database geanalyseerd (drempel: {$maanden} maanden, vóór {$drempel->toDateString()}).");
            $this->line("Totaal aantal gebruikers: {$totaal}");
            $this->newLine();

            $laatsteActiviteit = $this->laatsteActiviteitPerGebruiker($schema, $oud);

            if ($laatsteActiviteit === null) {
                $this->warn('Geen spreekbeurt-historie gevonden om activiteit te bepalen.');
                $this->line('Bij migratie worden alle gebruikers als actief geïmporteerd; opschoning gebeurt later via last_login_at.');

                return self::SUCCESS;
            }

            $zonderHistorie = $totaal - $laatsteActiviteit->count();
            $inactief = $laatsteActiviteit->filter(
                fn (string $datum): bool => strtotime($datum) < $drempel->getTimestamp()
            );
            $actief = $laatsteActiviteit->count() - $inactief->count();

            $this->line('Activiteit bepaald via laatste spreekbeurt-datum (proxy; er is geen login-data).');
            $this->newLine();
            $this->line("Actief als spreker (binnen {$maanden} mnd of toekomstige beurt): {$actief}");
            $this->line("Inactief als spreker (>{$maanden} mnd geen beurt):              {$inactief->count()}");
            $this->line("Nooit een spreekbeurt gehad (o.a. beheer/contactpersonen):     {$zonderHistorie}");
            $this->newLine();

            if ($inactief->isNotEmpty()) {
                $voorbeelden = $inactief->sort()->take($limit);
                $rows = $voorbeelden->map(function (string $datum, int $userId) use ($oud): array {
                    $u = $oud->table('users')->where('id', $userId)->first();

                    return [
                        $userId,
                        $u ? ($u->volledigeNaam() ?: '(naam onbekend)') : '(verwijderd)',
                        $u->email ?? '(geen e-mail)',
                        $datum,
                    ];
                })->values()->all();

                $this->line("Voorbeelden van langdurig inactieve sprekers (max {$limit}, oudste eerst):");
                $this->table(['ID', 'Naam', 'E-mail', 'Laatste spreekbeurt'], $rows);
                $this->newLine();
            }

            $this->info('Dit is een read-only informatief rapport. Er is niets gewijzigd.');
            $this->line('Conform afspraak: bij migratie worden alle gebruikers als actief geïmporteerd.');
            $this->line('Opschoning van inactieve accounts gebeurt later automatisch via last_login_at,');
            $this->line('zodra de nieuwe app in gebruik is. Gebruik dit rapport als naslag voor de klant.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Kon de oude database niet analyseren: '.$e->getMessage());
            $this->line('Controleer de OLD_DB_* instellingen in je .env.');

            return self::FAILURE;
        }
    }

    /**
     * Bouwt een map van user_id => laatste spreekbeurt-datum (Y-m-d),
     * gecombineerd over de oude tabellen `preekbeurten_sprekers`+`preekbeurten`
     * en `spreekbeurten`. Retourneert null als geen bron beschikbaar is.
     *
     * @return Collection<int, string>|null
     */
    private function laatsteActiviteitPerGebruiker(Builder $schema, Connection $oud): ?Collection
    {
        $laatste = collect();
        $bronGevonden = false;

        if ($schema->hasTable('preekbeurten_sprekers') && $schema->hasTable('preekbeurten')) {
            $bronGevonden = true;
            $oud->table('preekbeurten_sprekers as ps')
                ->join('preekbeurten as p', 'p.id', '=', 'ps.preekbeurt_id')
                ->selectRaw('ps.user_id, MAX(p.date) as laatste')
                ->groupBy('ps.user_id')
                ->get()
                ->each(function (object $r) use ($laatste): void {
                    $this->bewaarLaatste($laatste, (int) $r->user_id, (string) $r->laatste);
                });
        }

        if ($schema->hasTable('spreekbeurten') && $schema->hasColumn('spreekbeurten', 'spreker')) {
            $bronGevonden = true;
            $oud->table('spreekbeurten')
                ->selectRaw('spreker as user_id, MAX(datum) as laatste')
                ->whereNotNull('spreker')
                ->groupBy('spreker')
                ->get()
                ->each(function (object $r) use ($laatste): void {
                    $this->bewaarLaatste($laatste, (int) $r->user_id, (string) $r->laatste);
                });
        }

        return $bronGevonden ? $laatste : null;
    }

    /**
     * @param  Collection<int, string>  $laatste
     */
    private function bewaarLaatste(Collection $laatste, int $userId, string $datum): void
    {
        if ($userId <= 0 || $datum === '') {
            return;
        }

        $bestaand = $laatste->get($userId);

        if ($bestaand === null || strtotime($datum) > strtotime($bestaand)) {
            $laatste->put($userId, substr($datum, 0, 10));
        }
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
}

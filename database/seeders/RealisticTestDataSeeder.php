<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Functie;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class RealisticTestDataSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/realistic-test-snapshot.json');

        if (! is_file($path)) {
            throw new RuntimeException(
                "Snapshot ontbreekt: {$path}. Draai eerst `php artisan testdata:export-snapshot`."
            );
        }

        /** @var array<string, mixed> $snapshot */
        $snapshot = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        $snapshotDienstCount = count($snapshot['diensten'] ?? []);
        $snapshotGemeenteCount = count($snapshot['gemeentes'] ?? []);

        if ($snapshotGemeenteCount === 0 || $snapshotDienstCount === 0) {
            throw new RuntimeException(
                "Snapshot incompleet ({$path}): gemeentes={$snapshotGemeenteCount}, diensten={$snapshotDienstCount}. Upload database/data/realistic-test-snapshot.json opnieuw."
            );
        }

        $this->command?->info("Snapshot OK: {$snapshotGemeenteCount} gemeentes, {$snapshotDienstCount} diensten.");

        $anchorDate = Carbon::parse((string) ($snapshot['meta']['anchor_date'] ?? Carbon::today()->toDateString()))->startOfDay();
        // Alleen hele weken verschuiven — anders vallen sabbat-diensten naast
        // zaterdag en blijft de matrix leeg.
        $rawShiftDays = (int) $anchorDate->diffInDays(Carbon::today()->startOfDay(), false);
        $shiftDays = (int) round($rawShiftDays / 7) * 7;

        $passwordHash = Hash::make('password');
        $now = now();

        $roles = Role::query()->get()->keyBy('slug');
        $predikantFunctie = Functie::query()->where('slug', 'predikant')->first();
        $adminRole = $roles->get('admin');
        $predikantRole = $roles->get('predikant');
        $gebruikerRole = $roles->get('gebruiker');

        if (! $adminRole || ! $predikantRole || ! $gebruikerRole || ! $predikantFunctie) {
            throw new RuntimeException('Vereiste rollen/functies ontbreken. Draai eerst RoleSeeder + FunctieSeeder.');
        }

        DB::transaction(function () use (
            $snapshot,
            $shiftDays,
            $passwordHash,
            $now,
            $adminRole,
            $predikantRole,
            $gebruikerRole,
            $predikantFunctie
        ): void {
            foreach ($snapshot['districts'] ?? [] as $district) {
                DB::table('districts')->updateOrInsert(
                    ['id' => $district['id']],
                    [
                        'naam' => $district['naam'],
                        'visible' => (bool) ($district['visible'] ?? true),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }

            foreach ($snapshot['bijzonderheden'] ?? [] as $bijzonderheid) {
                DB::table('bijzonderheden')->updateOrInsert(
                    ['id' => $bijzonderheid['id']],
                    [
                        'naam' => $bijzonderheid['naam'],
                        'omschrijving' => $bijzonderheid['omschrijving'] ?? null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }

            $gemeenteIds = collect($snapshot['gemeentes'] ?? [])
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();
            $userIds = collect($snapshot['users'] ?? [])
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            foreach ($snapshot['gemeentes'] ?? [] as $gemeente) {
                $districtId = $gemeente['district_id'] ?? null;
                if ($districtId !== null && ! DB::table('districts')->where('id', $districtId)->exists()) {
                    $districtId = null;
                }

                DB::table('gemeentes')->updateOrInsert(
                    ['id' => $gemeente['id']],
                    [
                        'naam' => $gemeente['naam'],
                        'naam_kort' => $gemeente['naam_kort'] ?? null,
                        'adres' => $gemeente['adres'] ?? null,
                        'plaats' => $gemeente['plaats'] ?? null,
                        'postcode' => $gemeente['postcode'] ?? null,
                        'district_id' => $districtId,
                        'predikant_id' => null,
                        'contactpersoon_id' => null,
                        'begintijd_ochtend' => $gemeente['begintijd_ochtend'] ?? '10:00',
                        'begintijd_avond' => $gemeente['begintijd_avond'] ?? '18:30',
                        'kerk' => $gemeente['kerk'] ?? null,
                        'website_url' => $gemeente['website_url'] ?? null,
                        'livestream_url' => $gemeente['livestream_url'] ?? null,
                        'taal' => $gemeente['taal'] ?? 'nl',
                        'active' => (bool) ($gemeente['active'] ?? true),
                        'church_plant' => (bool) ($gemeente['church_plant'] ?? false),
                        'volgorde' => (int) ($gemeente['volgorde'] ?? 0),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }

            foreach ($snapshot['users'] ?? [] as $user) {
                $gemeenteId = $user['gemeente_id'] ?? null;
                if ($gemeenteId !== null && ! in_array($gemeenteId, $gemeenteIds, true)) {
                    $gemeenteId = null;
                }

                DB::table('users')->updateOrInsert(
                    ['id' => $user['id']],
                    [
                        'voornaam' => $user['voornaam'],
                        'tussenvoegsel' => $user['tussenvoegsel'] ?? null,
                        'achternaam' => $user['achternaam'],
                        'initialen' => $user['initialen'] ?? null,
                        'geslacht' => $user['geslacht'] ?? 'o',
                        'email' => $user['email'],
                        'password' => $passwordHash,
                        'taal' => $user['taal'] ?? 'nl',
                        'gemeente_id' => $gemeenteId,
                        'functie' => null,
                        'photo' => null,
                        'telefoonnummer' => null,
                        'mobiel' => null,
                        'two_factor_enabled' => false,
                        'active' => (bool) ($user['active'] ?? true),
                        'last_login_at' => $now->copy()->subDays(3),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );

                if (! empty($user['is_tester'])) {
                    DB::table('user_roles')->updateOrInsert(
                        ['user_id' => $user['id'], 'role_id' => $adminRole->id],
                        ['gemeente_id' => null, 'created_at' => $now, 'updated_at' => $now]
                    );
                } else {
                    DB::table('user_roles')->updateOrInsert(
                        ['user_id' => $user['id'], 'role_id' => $predikantRole->id],
                        ['gemeente_id' => $gemeenteId, 'created_at' => $now, 'updated_at' => $now]
                    );
                    DB::table('user_roles')->updateOrInsert(
                        ['user_id' => $user['id'], 'role_id' => $gebruikerRole->id],
                        ['gemeente_id' => null, 'created_at' => $now, 'updated_at' => $now]
                    );
                    DB::table('user_functies')->updateOrInsert(
                        ['user_id' => $user['id'], 'functie_id' => $predikantFunctie->id],
                        ['created_at' => $now, 'updated_at' => $now]
                    );
                }

                if ($gemeenteId !== null) {
                    DB::table('user_gemeentes')->updateOrInsert(
                        ['user_id' => $user['id'], 'gemeente_id' => $gemeenteId],
                        ['created_at' => $now, 'updated_at' => $now]
                    );
                }
            }

            foreach ($snapshot['gemeentes'] ?? [] as $gemeente) {
                $predikantId = $gemeente['predikant_id'] ?? null;
                $contactpersoonId = $gemeente['contactpersoon_id'] ?? null;

                if ($predikantId !== null && ! in_array($predikantId, $userIds, true)) {
                    $predikantId = null;
                }
                if ($contactpersoonId !== null && ! in_array($contactpersoonId, $userIds, true)) {
                    $contactpersoonId = null;
                }

                DB::table('gemeentes')->where('id', $gemeente['id'])->update([
                    'predikant_id' => $predikantId,
                    'contactpersoon_id' => $contactpersoonId,
                    'updated_at' => $now,
                ]);
            }

            /** @var array<int, int> $dienstIdByOldPreekbeurt */
            $dienstIdByOldPreekbeurt = [];
            /** @var array<string, int> $dienstIdByKey */
            $dienstIdByKey = [];

            foreach ($snapshot['diensten'] ?? [] as $dienst) {
                $gemeenteId = (int) $dienst['gemeente_id'];
                if (! in_array($gemeenteId, $gemeenteIds, true)) {
                    continue;
                }

                $datum = Carbon::parse((string) $dienst['datum'])->addDays($shiftDays)->toDateString();
                $key = $datum.'|'.$gemeenteId;

                if (! isset($dienstIdByKey[$key])) {
                    $existing = DB::table('diensten')
                        ->whereDate('datum', $datum)
                        ->where('gemeente_id', $gemeenteId)
                        ->first();

                    if ($existing) {
                        $dienstId = (int) $existing->id;
                        DB::table('diensten')->where('id', $dienstId)->update([
                            'type' => $this->mapDienstType($dienst['type'] ?? null),
                            'eigeninvulling' => $dienst['eigeninvulling'] ?? null,
                            'taal' => $dienst['taal'] ?? 'nl',
                            'dienstwijze' => 'fysiek',
                            'updated_at' => $now,
                        ]);
                    } else {
                        $dienstId = (int) DB::table('diensten')->insertGetId([
                            'datum' => $datum,
                            'gemeente_id' => $gemeenteId,
                            'type' => $this->mapDienstType($dienst['type'] ?? null),
                            'eigeninvulling' => $dienst['eigeninvulling'] ?? null,
                            'bijzonderheid_id' => null,
                            'taal' => $dienst['taal'] ?? 'nl',
                            'dienstwijze' => 'fysiek',
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }

                    $dienstIdByKey[$key] = $dienstId;
                }

                $dienstIdByOldPreekbeurt[(int) $dienst['old_id']] = $dienstIdByKey[$key];
            }

            $insertedDiensten = count($dienstIdByKey);
            if ($insertedDiensten === 0) {
                throw new RuntimeException(
                    'Geen diensten geinsert terwijl snapshot diensten bevat. Controleer gemeente_id-mapping en DB-schema (type/dienstwijze-kolommen).'
                );
            }

            foreach ($snapshot['spreekbeurten'] ?? [] as $spreekbeurt) {
                $oldPreekbeurtId = (int) $spreekbeurt['old_preekbeurt_id'];
                $dienstId = $dienstIdByOldPreekbeurt[$oldPreekbeurtId] ?? null;
                $sprekerId = (int) $spreekbeurt['spreker_id'];

                if ($dienstId === null || ! in_array($sprekerId, $userIds, true)) {
                    continue;
                }

                $ingevoerdDoor = $spreekbeurt['ingevoerd_door'] ?? null;
                if ($ingevoerdDoor !== null && ! in_array((int) $ingevoerdDoor, $userIds, true)) {
                    $ingevoerdDoor = null;
                }

                DB::table('spreekbeurten')->updateOrInsert(
                    [
                        'dienst_id' => $dienstId,
                        'spreker_id' => $sprekerId,
                    ],
                    [
                        'bevestigd' => $spreekbeurt['bevestigd'] ?? null,
                        'bericht' => null,
                        'kilometers' => $spreekbeurt['kilometers'] ?? null,
                        'ingevoerd_door' => $ingevoerdDoor,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }

            // Meer "open plek" in het publieke rooster:
            // ~1/3 onbevestigd (toont als open plek), ~1/7 zonder spreker.
            $futureDienstIds = DB::table('diensten')
                ->whereDate('datum', '>=', Carbon::today()->toDateString())
                ->orderBy('id')
                ->pluck('id');

            $unconfirmedIds = $futureDienstIds
                ->filter(static fn (int $id, int $index): bool => $index % 3 === 0)
                ->values();
            $withoutSpeakerIds = $futureDienstIds
                ->filter(static fn (int $id, int $index): bool => $index % 7 === 0)
                ->values();

            if ($unconfirmedIds->isNotEmpty()) {
                DB::table('spreekbeurten')
                    ->whereIn('dienst_id', $unconfirmedIds->all())
                    ->update(['bevestigd' => null, 'updated_at' => $now]);
            }
            if ($withoutSpeakerIds->isNotEmpty()) {
                DB::table('spreekbeurten')
                    ->whereIn('dienst_id', $withoutSpeakerIds->all())
                    ->delete();
            }

            $adminId = $this->upsertAdmin($passwordHash, $adminRole->id, $now);

            // Publiek rooster: huidige maand + komende 2 maanden.
            foreach ([
                Carbon::today()->format('Y-m'),
                Carbon::today()->addMonth()->format('Y-m'),
                Carbon::today()->addMonths(2)->format('Y-m'),
            ] as $periode) {
                DB::table('publicaties')->updateOrInsert(
                    ['periode' => $periode, 'gemeente_id' => null],
                    [
                        'gepubliceerd' => true,
                        'gepubliceerd_op' => $now,
                        'gepubliceerd_door' => $adminId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }

            $this->command?->info(
                "Seed klaar: diensten={$insertedDiensten}, spreekbeurten-links=".count($dienstIdByOldPreekbeurt).', shiftDays='.$shiftDays
            );
        });

        $dbDiensten = DB::table('diensten')->count();
        if ($dbDiensten === 0) {
            throw new RuntimeException('Na seed staan er 0 diensten in de database — seed is mislukt.');
        }

        $this->command?->info("DB-check: diensten={$dbDiensten}, publicaties=".DB::table('publicaties')->count());
    }

    private function upsertAdmin(string $passwordHash, int $adminRoleId, Carbon $now): int
    {
        $email = 'internet@advice.nl';

        $existing = DB::table('users')->where('email', $email)->first()
            ?? DB::table('users')->where('email', 'admin@example.test')->first();

        if ($existing) {
            $adminId = (int) $existing->id;
            DB::table('users')->where('id', $adminId)->update([
                'voornaam' => 'Internet',
                'tussenvoegsel' => null,
                'achternaam' => 'Beheer',
                'initialen' => 'IB',
                'geslacht' => 'o',
                'email' => $email,
                'password' => $passwordHash,
                'taal' => 'nl',
                'two_factor_enabled' => false,
                'active' => true,
                'last_login_at' => $now->copy()->subDays(2),
                'updated_at' => $now,
            ]);
        } else {
            $adminId = (int) DB::table('users')->insertGetId([
                'voornaam' => 'Internet',
                'tussenvoegsel' => null,
                'achternaam' => 'Beheer',
                'initialen' => 'IB',
                'geslacht' => 'o',
                'email' => $email,
                'password' => $passwordHash,
                'taal' => 'nl',
                'gemeente_id' => null,
                'functie' => null,
                'photo' => null,
                'telefoonnummer' => null,
                'mobiel' => null,
                'two_factor_enabled' => false,
                'active' => true,
                'last_login_at' => $now->copy()->subDays(2),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('user_roles')->updateOrInsert(
            ['user_id' => $adminId, 'role_id' => $adminRoleId],
            ['gemeente_id' => null, 'created_at' => $now, 'updated_at' => $now]
        );

        return $adminId;
    }

    private function mapDienstType(mixed $type): string
    {
        return match ($type) {
            'dienst', 'reguliere_dienst', 'bijzonder' => (string) $type,
            'speciaal' => 'bijzonder',
            'sabbatschool', 'eredienst' => 'reguliere_dienst',
            default => 'reguliere_dienst',
        };
    }
}

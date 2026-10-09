<?php

declare(strict_types=1);

use App\Services\Migratie\OudeDataMapper;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

DB::disableQueryLog();

set_time_limit(0);

echo "Start datamigratie...\n";

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

$oud = DB::connection('oud');
$mapper = new OudeDataMapper;
$nu = now();

$rollen = DB::table('roles')->pluck('id', 'slug');
$functies = DB::table('functies')->pluck('id', 'slug');

foreach (['admin', 'gebruiker'] as $slug) {
    if (! isset($rollen[$slug])) {
        throw new RuntimeException("Rol \"{$slug}\" ontbreekt. Draai eerst RoleSeeder.");
    }
}

foreach (['predikant', 'spreker', 'contactpersoon'] as $slug) {
    if (! isset($functies[$slug])) {
        throw new RuntimeException("Functie \"{$slug}\" ontbreekt. Draai eerst FunctieSeeder.");
    }
}

foreach ($mapper->talen() as $taal) {
    DB::table('talen')->updateOrInsert(
        ['slug' => $taal['slug']],
        ['naam' => $taal['naam'], 'created_at' => $nu, 'updated_at' => $nu]
    );
}

$taalIds = DB::table('talen')->pluck('id', 'slug');

DB::transaction(function () use ($oud, $mapper, $nu, $rollen, $functies, $taalIds): void {
    echo "Districts migreren...\n";
    $oudDistricts = $oud->table('districts')->get();
    $heeftIdDrie = $oudDistricts->contains(fn (object $d): bool => (int) $d->id === OudeDataMapper::DISTRICT_ZERO_ID);
    if ($heeftIdDrie && $oudDistricts->contains(fn (object $d): bool => (int) $d->id === 0)) {
        throw new RuntimeException('District id 0 kan niet naar id 3: dat id bestaat al in de oude database.');
    }

    foreach ($oudDistricts as $d) {
        $id = $mapper->districtId($d->id);
        if ($id === null) {
            continue;
        }

        DB::table('districts')->updateOrInsert(
            ['id' => $id],
            [
                'naam' => $mapper->naam($d->naam ?? $d->district ?? null),
                'visible' => true,
                'created_at' => $nu,
                'updated_at' => $nu,
            ]
        );
    }
    echo count($oudDistricts)." districts gemigreerd.\n";

    $districtIds = DB::table('districts')->pluck('id')->flip();

    echo "Gemeentes migreren...\n";
    $oudGemeentes = $oud->table('gemeentes')->get();
    foreach ($oudGemeentes as $g) {
        $districtId = $mapper->districtId($g->district ?? null);
        if ($districtId !== null && ! isset($districtIds[$districtId])) {
            $districtId = null;
        }

        DB::table('gemeentes')->updateOrInsert(
            ['id' => $g->id],
            [
                'naam' => $mapper->naam($g->gemeente ?? null),
                'naam_kort' => leegNaarNull($g->gemeente_kort ?? null),
                'adres' => leegNaarNull($g->adres ?? null),
                'plaats' => leegNaarNull($g->plaats ?? null),
                'postcode' => leegNaarNull($g->postcode ?? null),
                'district_id' => $districtId,
                'begintijd_ochtend' => $mapper->begintijd($g->begintijd_ss ?? null, '10:00'),
                'begintijd_avond' => $mapper->begintijd($g->begintijd_ed ?? null, '11:00'),
                'kerk' => leegNaarNull($g->kerk ?? null),
                'website_url' => leegNaarNull($g->website_url ?? null),
                'livestream_url' => leegNaarNull($g->livestream_url ?? null),
                'taal' => $mapper->gemeenteTaal($g->taal ?? null),
                'active' => (bool) ($g->active ?? true),
                'church_plant' => (bool) ($g->church_plant ?? false),
                'volgorde' => (int) ($g->volgorde ?? 0),
                'created_at' => $nu,
                'updated_at' => $nu,
            ]
        );
    }
    echo count($oudGemeentes)." gemeentes gemigreerd.\n";

    $gemeenteIds = DB::table('gemeentes')->pluck('id')->flip();

    if ($oud->getSchemaBuilder()->hasTable('bijzonderheden')) {
        echo "Bijzonderheden migreren...\n";
        $oudBijzonderheden = $oud->table('bijzonderheden')->get();
        foreach ($oudBijzonderheden as $b) {
            DB::table('bijzonderheden')->updateOrInsert(
                ['id' => $b->id],
                [
                    'naam' => $mapper->naam($b->bijzonderheid ?? $b->naam ?? null),
                    'omschrijving' => leegNaarNull($b->kortnaam ?? $b->omschrijving ?? null),
                    'created_at' => $nu,
                    'updated_at' => $nu,
                ]
            );
        }
        echo count($oudBijzonderheden)." bijzonderheden gemigreerd.\n";
    }

    $bijzonderheidIds = DB::table('bijzonderheden')->pluck('id')->flip();

    $adminIds = [];
    if ($oud->getSchemaBuilder()->hasTable('rechten')) {
        $adminIds = $oud->table('rechten')
            ->where('wat', 'admin')
            ->pluck('persoon')
            ->map(fn (mixed $id): int => (int) $id)
            ->flip()
            ->all();
    }

    echo "Gebruikers migreren...\n";
    $oudUsers = $oud->table('users')->get();
    $afsprakenNietLeeg = 0;

    foreach ($oudUsers as $u) {
        $userId = (int) $u->id;
        $identiteit = $mapper->identiteit($u->functie ?? null, isset($adminIds[$userId]));
        $gemeenteId = isset($u->gemeente) && isset($gemeenteIds[(int) $u->gemeente])
            ? (int) $u->gemeente
            : null;

        DB::table('users')->updateOrInsert(
            ['id' => $userId],
            [
                'voornaam' => $mapper->naam($u->voornaam ?? null),
                'tussenvoegsel' => leegNaarNull($u->tussenvoegsel ?? null),
                'achternaam' => $mapper->naam($u->achternaam ?? null),
                'initialen' => leegNaarNull($u->initialen ?? null),
                'geslacht' => $mapper->geslacht($u->geslacht ?? null),
                'email' => $mapper->email($u->email ?? null, $userId),
                'password' => filled($u->password ?? null)
                    ? (string) $u->password
                    : Hash::make(Str::password(64)),
                'taal' => $mapper->userTaal($u->taal ?? null),
                'gemeente_id' => $gemeenteId,
                'functie' => $identiteit['functie'],
                'photo' => leegNaarNull($u->photo ?? null),
                'telefoonnummer' => leegNaarNull($u->telefoonnummer ?? null),
                'mobiel' => leegNaarNull($u->mobiel ?? null),
                'spreekniveau' => $identiteit['spreekniveau'],
                'landelijk_actief' => $identiteit['landelijk_actief'],
                'two_factor_enabled' => true,
                'active' => (bool) ($u->active ?? true),
                'last_login_at' => null,
                'created_at' => $nu,
                'updated_at' => $nu,
            ]
        );

        DB::table('user_roles')->where('user_id', $userId)->delete();
        foreach ($identiteit['rollen'] as $rolSlug) {
            DB::table('user_roles')->insert([
                'user_id' => $userId,
                'role_id' => $rollen[$rolSlug],
                'gemeente_id' => null,
                'created_at' => $nu,
                'updated_at' => $nu,
            ]);
        }

        DB::table('user_functies')->where('user_id', $userId)->delete();
        if ($identiteit['functie'] !== null) {
            DB::table('user_functies')->insert([
                'user_id' => $userId,
                'functie_id' => $functies[$identiteit['functie']],
                'created_at' => $nu,
                'updated_at' => $nu,
            ]);
        }

        DB::table('user_gemeentes')->where('user_id', $userId)->delete();
        if ($gemeenteId !== null) {
            DB::table('user_gemeentes')->insert([
                'user_id' => $userId,
                'gemeente_id' => $gemeenteId,
                'created_at' => $nu,
                'updated_at' => $nu,
            ]);
        }

        if (trim((string) ($u->afspraken ?? '')) !== '') {
            $afsprakenNietLeeg++;
        }
    }
    echo count($oudUsers)." gebruikers gemigreerd.\n";
    if ($afsprakenNietLeeg > 0) {
        echo "{$afsprakenNietLeeg} gebruikers hebben vrije tekst in afspraken; die is niet als beschikbaarheidsperiode opgeslagen.\n";
    }

    if ($oud->getSchemaBuilder()->hasTable('user_language')) {
        echo "Talen migreren...\n";
        DB::table('user_talen')->whereIn('user_id', $oudUsers->pluck('id')->all())->delete();
        $gekoppeld = 0;
        foreach ($oud->table('user_language')->get() as $rij) {
            $userId = (int) $rij->user_id;
            $slug = $mapper->taalSlug($rij->language ?? null);
            if ($slug === null || ! isset($taalIds[$slug]) || ! DB::table('users')->where('id', $userId)->exists()) {
                continue;
            }

            DB::table('user_talen')->updateOrInsert(
                ['user_id' => $userId, 'taal_id' => $taalIds[$slug]],
                ['created_at' => $nu, 'updated_at' => $nu]
            );
            $gekoppeld++;
        }
        echo "{$gekoppeld} taalkoppelingen gemigreerd.\n";
    }

    $userIds = DB::table('users')->pluck('id')->flip();

    echo "Predikant en contactpersoon op gemeentes zetten...\n";
    foreach ($oudGemeentes as $g) {
        $predikantId = isset($g->predikant) && isset($userIds[(int) $g->predikant]) ? (int) $g->predikant : null;
        $contactId = isset($g->contactpersoon) && isset($userIds[(int) $g->contactpersoon]) ? (int) $g->contactpersoon : null;

        DB::table('gemeentes')->where('id', $g->id)->update([
            'predikant_id' => $predikantId,
            'contactpersoon_id' => $contactId,
            'updated_at' => $nu,
        ]);
    }

    if ($oud->getSchemaBuilder()->hasTable('rechten')) {
        foreach ($oud->table('rechten')->where('wat', 'gemeente')->get() as $recht) {
            $userId = (int) $recht->persoon;
            $gemeenteId = (int) $recht->watid;
            if (! isset($userIds[$userId]) || ! isset($gemeenteIds[$gemeenteId])) {
                continue;
            }

            DB::table('user_gemeentes')->updateOrInsert(
                ['user_id' => $userId, 'gemeente_id' => $gemeenteId],
                ['created_at' => $nu, 'updated_at' => $nu]
            );
        }
    }

    if ($oud->getSchemaBuilder()->hasTable('preekbeurten')) {
        echo "Diensten migreren...\n";
        $groepen = [];
        $overgeslagen = 0;
        foreach ($oud->table('preekbeurten')->get() as $p) {
            $gemeenteId = (int) ($p->gemeente ?? 0);
            $datum = (string) ($p->date ?? $p->datum ?? '');
            if ($gemeenteId <= 0 || $datum === '' || ! isset($gemeenteIds[$gemeenteId])) {
                $overgeslagen++;

                continue;
            }

            $groepen[$datum.'|'.$gemeenteId][] = $p;
        }

        foreach ($groepen as $preekbeurten) {
            $kandidaten = array_map(
                fn (object $p): array => ['id' => (int) $p->id, 'kind' => $p->kind ?? null, 'rij' => $p],
                $preekbeurten
            );
            $gekozen = $mapper->kiesPreekbeurt($kandidaten);
            $p = $gekozen['rij'];
            $bijzonderheidId = $mapper->bijzonderheidId($p->kind ?? $p->bijzonderheid_id ?? null);
            if ($bijzonderheidId !== null && ! isset($bijzonderheidIds[$bijzonderheidId])) {
                $bijzonderheidId = null;
            }

            DB::table('diensten')->updateOrInsert(
                [
                    'datum' => (string) ($p->date ?? $p->datum),
                    'gemeente_id' => (int) $p->gemeente,
                ],
                [
                    'type' => 'sabbatschool',
                    'eigeninvulling' => leegNaarNull($p->eigeninvulling ?? null) !== null
                        ? mb_substr((string) $p->eigeninvulling, 0, 255)
                        : null,
                    'bijzonderheid_id' => $bijzonderheidId,
                    'taal' => $mapper->dienstTaal($p->language ?? null),
                    'dienstwijze' => 'fysiek',
                    'created_at' => $nu,
                    'updated_at' => $nu,
                ]
            );
        }

        echo count($groepen)." diensten gemigreerd, {$overgeslagen} preekbeurten overgeslagen.\n";
    }

    $dienstIds = [];
    foreach (DB::table('diensten')->select('id', 'datum', 'gemeente_id')->get() as $dienst) {
        $datum = substr((string) $dienst->datum, 0, 10);
        $dienstIds[$datum.'|'.$dienst->gemeente_id] = (int) $dienst->id;
    }

    if ($oud->getSchemaBuilder()->hasTable('preekbeurten_sprekers') && $oud->getSchemaBuilder()->hasTable('preekbeurten')) {
        echo "Spreekbeurten migreren (bron: preekbeurten_sprekers)...\n";
        $rijen = $oud->table('preekbeurten_sprekers as ps')
            ->join('preekbeurten as p', 'p.id', '=', 'ps.preekbeurt_id')
            ->select([
                'p.date as datum',
                'p.gemeente',
                'ps.user_id as spreker',
                'ps.confirmed as bevestigd',
                'ps.kilometers',
                'ps.entered_by_user_id as ingevoerd_door',
                'ps.timestamp as ingevoerd_op',
            ])
            ->get();
        $geschreven = schrijfSpreekbeurten($rijen, $dienstIds, $userIds, $mapper, $nu);
        echo "{$geschreven} spreekbeurten gemigreerd, ".(count($rijen) - $geschreven)." overgeslagen.\n";
    } elseif ($oud->getSchemaBuilder()->hasTable('spreekbeurten')) {
        echo "Spreekbeurten migreren (fallback bron: spreekbeurten)...\n";
        $rijen = $oud->table('spreekbeurten')->get();
        $geschreven = schrijfSpreekbeurten($rijen, $dienstIds, $userIds, $mapper, $nu, true);
        echo "{$geschreven} spreekbeurten gemigreerd, ".(count($rijen) - $geschreven)." overgeslagen.\n";
    }
});

echo "Klaar! Controleer de data in de nieuwe database.\n";

function leegNaarNull(mixed $waarde): ?string
{
    if ($waarde === null) {
        return null;
    }

    $tekst = trim((string) $waarde);

    return $tekst === '' ? null : $tekst;
}

/**
 * @param  iterable<int, object>  $rijen
 * @param  array<string, int>  $dienstIds
 * @param  Collection<int|string, mixed>  $userIds
 */
function schrijfSpreekbeurten(iterable $rijen, array $dienstIds, $userIds, OudeDataMapper $mapper, mixed $nu, bool $metBericht = false): int
{
    $geschreven = 0;

    foreach ($rijen as $s) {
        $gemeenteId = (int) ($s->gemeente ?? 0);
        $sprekerId = (int) ($s->spreker ?? 0);
        $datum = substr((string) ($s->datum ?? ''), 0, 10);
        $dienstId = $dienstIds[$datum.'|'.$gemeenteId] ?? null;

        if ($dienstId === null || $sprekerId <= 0 || ! isset($userIds[$sprekerId])) {
            continue;
        }

        $ingevoerdDoor = isset($s->ingevoerd_door) && isset($userIds[(int) $s->ingevoerd_door])
            ? (int) $s->ingevoerd_door
            : null;

        $waarden = [
            'bevestigd' => $mapper->bevestigd($s->bevestigd ?? null),
            'bericht' => $metBericht ? leegNaarNull($s->bericht ?? null) : null,
            'kilometers' => $s->kilometers !== null && $s->kilometers !== '' ? (int) $s->kilometers : null,
            'ingevoerd_door' => $ingevoerdDoor,
            'created_at' => $s->ingevoerd_op ?? $nu,
            'updated_at' => $nu,
        ];

        DB::table('spreekbeurten')->updateOrInsert(
            ['dienst_id' => $dienstId, 'spreker_id' => $sprekerId],
            $waarden
        );
        $geschreven++;
    }

    return $geschreven;
}

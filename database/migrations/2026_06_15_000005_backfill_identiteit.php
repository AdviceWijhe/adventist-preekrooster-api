<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Backfill voor het nieuwe identiteitsmodel: zet bestaande toegangsrollen
 * `predikant` en `contactpersoon` om naar functies (predikant /
 * gemeentesecretaris) met behoud van een `gebruiker`-toegangsrol, en vul de
 * many-to-many gemeente-koppeling vanuit de bestaande enkelvoudige velden.
 *
 * Idempotent en veilig op een lege database (fresh installs doen niets).
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $functieIds = $this->ensureFuncties($now);
        $rolIds = $this->ensureRollen($now);

        $this->backfillFunctiesVanuitRollen($functieIds, $rolIds, $now);
        $this->backfillGemeenteKoppelingen($now);
    }

    public function down(): void
    {
        // Geen rollback: dit is een data-backfill. De tabellen zelf worden door
        // hun eigen migraties teruggedraaid.
    }

    /**
     * @param  Carbon  $now
     * @return array<string, int>
     */
    private function ensureFuncties($now): array
    {
        $functies = [
            'spreker' => 'Spreker',
            'predikant' => 'Predikant',
            'gemeentesecretaris' => 'Gemeentesecretaris',
            'ouderling' => 'Ouderling',
            'lekenprediker' => 'Lekenprediker',
        ];

        $ids = [];

        foreach ($functies as $slug => $naam) {
            DB::table('functies')->updateOrInsert(
                ['slug' => $slug],
                ['naam' => $naam, 'updated_at' => $now, 'created_at' => $now]
            );
            $ids[$slug] = (int) DB::table('functies')->where('slug', $slug)->value('id');
        }

        return $ids;
    }

    /**
     * @param  Carbon  $now
     * @return array<string, int>
     */
    private function ensureRollen($now): array
    {
        DB::table('roles')->updateOrInsert(
            ['slug' => 'gebruiker'],
            ['naam' => 'Gebruiker', 'updated_at' => $now, 'created_at' => $now]
        );

        return DB::table('roles')->pluck('id', 'slug')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * @param  array<string, int>  $functieIds
     * @param  array<string, int>  $rolIds
     * @param  Carbon  $now
     */
    private function backfillFunctiesVanuitRollen(array $functieIds, array $rolIds, $now): void
    {
        $mapping = [
            'predikant' => 'predikant',
            'contactpersoon' => 'gemeentesecretaris',
        ];

        foreach ($mapping as $rolSlug => $functieSlug) {
            if (! isset($rolIds[$rolSlug], $functieIds[$functieSlug])) {
                continue;
            }

            $userIds = DB::table('user_roles')
                ->where('role_id', $rolIds[$rolSlug])
                ->pluck('user_id')
                ->unique();

            foreach ($userIds as $userId) {
                DB::table('user_functies')->updateOrInsert(
                    ['user_id' => $userId, 'functie_id' => $functieIds[$functieSlug]],
                    ['updated_at' => $now, 'created_at' => $now]
                );

                if (isset($rolIds['gebruiker'])) {
                    DB::table('user_roles')->updateOrInsert(
                        ['user_id' => $userId, 'role_id' => $rolIds['gebruiker']],
                        ['updated_at' => $now, 'created_at' => $now]
                    );
                }
            }
        }
    }

    /**
     * @param  Carbon  $now
     */
    private function backfillGemeenteKoppelingen($now): void
    {
        // Vanuit users.gemeente_id
        DB::table('users')->whereNotNull('gemeente_id')->orderBy('id')->each(function ($user) use ($now): void {
            $this->koppelGemeente((int) $user->id, (int) $user->gemeente_id, $now);
        });

        // Vanuit user_roles.gemeente_id (rol-scope)
        DB::table('user_roles')->whereNotNull('gemeente_id')->orderBy('id')->each(function ($rol) use ($now): void {
            $this->koppelGemeente((int) $rol->user_id, (int) $rol->gemeente_id, $now);
        });
    }

    /**
     * @param  Carbon  $now
     */
    private function koppelGemeente(int $userId, int $gemeenteId, $now): void
    {
        if ($gemeenteId <= 0) {
            return;
        }

        if (! DB::table('gemeentes')->where('id', $gemeenteId)->exists()) {
            return;
        }

        DB::table('user_gemeentes')->updateOrInsert(
            ['user_id' => $userId, 'gemeente_id' => $gemeenteId],
            ['updated_at' => $now, 'created_at' => $now]
        );
    }
};

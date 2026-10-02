<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('functies')->updateOrInsert(
            ['slug' => 'contactpersoon'],
            ['naam' => 'Contactpersoon', 'updated_at' => $now, 'created_at' => $now]
        );

        $ids = DB::table('functies')->pluck('id', 'slug');

        $sprekerId = (int) ($ids['spreker'] ?? 0);
        $contactId = (int) ($ids['contactpersoon'] ?? 0);
        $lekenId = isset($ids['lekenprediker']) ? (int) $ids['lekenprediker'] : null;
        $secretarisId = isset($ids['gemeentesecretaris']) ? (int) $ids['gemeentesecretaris'] : null;
        $ouderlingId = isset($ids['ouderling']) ? (int) $ids['ouderling'] : null;

        if ($lekenId !== null && $sprekerId > 0) {
            $userIds = DB::table('user_functies')->where('functie_id', $lekenId)->pluck('user_id');
            foreach ($userIds as $userId) {
                $exists = DB::table('user_functies')
                    ->where('user_id', $userId)
                    ->where('functie_id', $sprekerId)
                    ->exists();
                if (! $exists) {
                    DB::table('user_functies')->insert([
                        'user_id' => $userId,
                        'functie_id' => $sprekerId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
            DB::table('user_functies')->where('functie_id', $lekenId)->delete();
        }

        if ($secretarisId !== null && $contactId > 0) {
            $userIds = DB::table('user_functies')->where('functie_id', $secretarisId)->pluck('user_id');
            foreach ($userIds as $userId) {
                $exists = DB::table('user_functies')
                    ->where('user_id', $userId)
                    ->where('functie_id', $contactId)
                    ->exists();
                if (! $exists) {
                    DB::table('user_functies')->insert([
                        'user_id' => $userId,
                        'functie_id' => $contactId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
            DB::table('user_functies')->where('functie_id', $secretarisId)->delete();
        }

        if ($ouderlingId !== null) {
            DB::table('user_functies')->where('functie_id', $ouderlingId)->delete();
        }

        DB::table('functies')->whereIn('slug', [
            'lekenprediker',
            'gemeentesecretaris',
            'ouderling',
        ])->delete();
    }

    public function down(): void
    {
        $now = now();
        foreach (
            [
                'lekenprediker' => 'Lekenprediker',
                'gemeentesecretaris' => 'Gemeentesecretaris',
                'ouderling' => 'Ouderling',
            ] as $slug => $naam
        ) {
            DB::table('functies')->updateOrInsert(
                ['slug' => $slug],
                ['naam' => $naam, 'updated_at' => $now, 'created_at' => $now]
            );
        }
    }
};

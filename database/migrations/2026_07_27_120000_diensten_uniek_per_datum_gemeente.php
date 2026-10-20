<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('diensten')
            ->select('datum', 'gemeente_id')
            ->groupBy('datum', 'gemeente_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $group) {
            $keepId = DB::table('diensten')
                ->where('datum', $group->datum)
                ->where('gemeente_id', $group->gemeente_id)
                ->min('id');

            DB::table('diensten')
                ->where('datum', $group->datum)
                ->where('gemeente_id', $group->gemeente_id)
                ->where('id', '!=', $keepId)
                ->delete();
        }

        Schema::table('diensten', function (Blueprint $table): void {
            $table->dropUnique(['datum', 'gemeente_id', 'type']);
            $table->unique(['datum', 'gemeente_id']);
        });
    }

    public function down(): void
    {
        Schema::table('diensten', function (Blueprint $table): void {
            $table->dropUnique(['datum', 'gemeente_id']);
            $table->unique(['datum', 'gemeente_id', 'type']);
        });
    }
};

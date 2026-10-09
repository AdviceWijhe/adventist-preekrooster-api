<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('diensten')->whereIn('type', ['sabbatschool', 'eredienst'])->update(['type' => 'reguliere_dienst']);
        DB::table('diensten')->where('type', 'speciaal')->update(['type' => 'bijzonder']);

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE diensten MODIFY COLUMN type VARCHAR(32) NOT NULL DEFAULT 'reguliere_dienst'");
        }
    }

    public function down(): void
    {
        DB::table('diensten')->where('type', 'reguliere_dienst')->update(['type' => 'eredienst']);
        DB::table('diensten')->where('type', 'dienst')->update(['type' => 'eredienst']);
        DB::table('diensten')->where('type', 'bijzonder')->update(['type' => 'speciaal']);

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE diensten MODIFY COLUMN type VARCHAR(32) NOT NULL DEFAULT 'sabbatschool'");
        }
    }
};

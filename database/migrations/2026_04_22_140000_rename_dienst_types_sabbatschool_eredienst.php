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
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $this->upSqlite();

            return;
        }

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE diensten MODIFY COLUMN type VARCHAR(32) NOT NULL DEFAULT 'ochtend'");
        }

        DB::table('diensten')->where('type', 'ochtend')->update(['type' => 'sabbatschool']);
        DB::table('diensten')->where('type', 'avond')->update(['type' => 'eredienst']);

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE diensten MODIFY COLUMN type VARCHAR(32) NOT NULL DEFAULT 'sabbatschool'");
        }
    }

    /**
     * SQLite legt op enum-kolommen een CHECK achter. Vervang `type` zonder de constraint.
     */
    private function upSqlite(): void
    {
        Schema::table('diensten', function (Blueprint $table) {
            $table->dropUnique(['datum', 'gemeente_id', 'type']);
        });
        Schema::table('diensten', function (Blueprint $table) {
            $table->string('type_new', 32)->default('sabbatschool');
        });
        DB::update(
            "UPDATE diensten SET type_new = CASE type
                WHEN 'ochtend' THEN 'sabbatschool'
                WHEN 'avond' THEN 'eredienst'
                ELSE type
            END"
        );
        Schema::table('diensten', function (Blueprint $table) {
            $table->dropColumn('type');
        });
        DB::statement('ALTER TABLE diensten RENAME COLUMN type_new TO type');
        Schema::table('diensten', function (Blueprint $table) {
            $table->unique(['datum', 'gemeente_id', 'type']);
        });
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE diensten MODIFY COLUMN type VARCHAR(32) NOT NULL DEFAULT 'sabbatschool'");
        }

        DB::table('diensten')->where('type', 'sabbatschool')->update(['type' => 'ochtend']);
        DB::table('diensten')->where('type', 'eredienst')->update(['type' => 'avond']);

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE diensten MODIFY COLUMN type ENUM('ochtend','avond','speciaal') NOT NULL DEFAULT 'ochtend'");
        }
    }
};

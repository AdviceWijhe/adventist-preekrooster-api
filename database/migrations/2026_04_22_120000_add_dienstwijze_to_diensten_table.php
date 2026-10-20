<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diensten', function (Blueprint $table): void {
            $table->string('dienstwijze', 32)->default('fysiek')->after('taal');
        });
    }

    public function down(): void
    {
        Schema::table('diensten', function (Blueprint $table): void {
            $table->dropColumn('dienstwijze');
        });
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreign('gemeente_id')->references('id')->on('gemeentes')->nullOnDelete();
        });

        Schema::table('user_roles', function (Blueprint $table): void {
            $table->foreign('gemeente_id')->references('id')->on('gemeentes')->nullOnDelete();
        });

        Schema::table('gemeentes', function (Blueprint $table): void {
            $table->foreign('predikant_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('contactpersoon_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('gemeentes', function (Blueprint $table): void {
            $table->dropForeign(['predikant_id']);
            $table->dropForeign(['contactpersoon_id']);
        });

        Schema::table('user_roles', function (Blueprint $table): void {
            $table->dropForeign(['gemeente_id']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['gemeente_id']);
        });
    }
};

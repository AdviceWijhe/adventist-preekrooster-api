<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('two_factor_codes', function (Blueprint $table): void {
            $table->string('code', 255)->change();
        });
    }

    public function down(): void
    {
        Schema::table('two_factor_codes', function (Blueprint $table): void {
            $table->string('code', 6)->change();
        });
    }
};

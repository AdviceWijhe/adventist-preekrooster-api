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
            $table->timestamp('avg_consent_at')->nullable()->after('inactiviteit_waarschuwing_at');
            $table->timestamp('avg_refused_at')->nullable()->after('avg_consent_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['avg_consent_at', 'avg_refused_at']);
        });
    }
};

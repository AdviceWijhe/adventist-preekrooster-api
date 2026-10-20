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
            $table->string('agenda_token', 64)->nullable()->unique()->after('last_login_at');
            $table->timestamp('agenda_token_created_at')->nullable()->after('agenda_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['agenda_token', 'agenda_token_created_at']);
        });
    }
};

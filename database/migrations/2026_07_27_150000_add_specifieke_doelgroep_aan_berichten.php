<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('berichten', function (Blueprint $table): void {
            $table->json('gemeente_ids')->nullable()->after('doelgroep');
            $table->json('gebruiker_ids')->nullable()->after('gemeente_ids');
        });
    }

    public function down(): void
    {
        Schema::table('berichten', function (Blueprint $table): void {
            $table->dropColumn(['gemeente_ids', 'gebruiker_ids']);
        });
    }
};

<?php

declare(strict_types=1);

use App\Models\Option;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('options')) {
            return;
        }

        $primaryReplacements = ['#2563EB', '#2563eb', '#2E5E4E', '#2e5e4e'];
        foreach ($primaryReplacements as $old) {
            Option::query()
                ->where('naam', 'branding_primary_color')
                ->where('waarde', $old)
                ->update(['waarde' => '#2F557F']);
        }

        $secondaryReplacements = ['#0F172A', '#0f172a', '#1F2A26', '#1f2a26'];
        foreach ($secondaryReplacements as $old) {
            Option::query()
                ->where('naam', 'branding_secondary_color')
                ->where('waarde', $old)
                ->update(['waarde' => '#04132B']);
        }
    }

    public function down(): void
    {
        // Geen rollback: oude kleuren zijn vervangen.
    }
};

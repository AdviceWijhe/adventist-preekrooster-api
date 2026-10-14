<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gemeente_rooster_sluitingen', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gemeente_id')->constrained('gemeentes')->cascadeOnDelete();
            $table->date('datum');
            $table->timestamps();

            $table->unique(['gemeente_id', 'datum']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gemeente_rooster_sluitingen');
    }
};

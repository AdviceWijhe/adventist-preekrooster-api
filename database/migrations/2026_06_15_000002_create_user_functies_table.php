<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_functies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('functie_id')->constrained('functies')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'functie_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_functies');
    }
};

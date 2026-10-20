<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diensten', function (Blueprint $table): void {
            $table->id();
            $table->date('datum');
            $table->foreignId('gemeente_id')->constrained('gemeentes')->cascadeOnDelete();
            $table->enum('type', ['ochtend', 'avond', 'speciaal'])->default('ochtend');
            $table->string('eigeninvulling')->nullable();
            $table->foreignId('bijzonderheid_id')->nullable()->constrained('bijzonderheden')->nullOnDelete();
            $table->string('taal', 10)->default('nl');
            $table->timestamps();

            $table->unique(['datum', 'gemeente_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diensten');
    }
};

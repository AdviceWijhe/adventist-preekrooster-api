<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_talen', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('taal_id')->constrained('talen')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'taal_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_talen');
    }
};

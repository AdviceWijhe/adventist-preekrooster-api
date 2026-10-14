<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_gemeentes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gemeente_id')->constrained('gemeentes')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'gemeente_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_gemeentes');
    }
};

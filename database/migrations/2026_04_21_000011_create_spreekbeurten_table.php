<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spreekbeurten', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dienst_id')->constrained('diensten')->cascadeOnDelete();
            $table->foreignId('spreker_id')->constrained('users')->cascadeOnDelete();
            $table->tinyInteger('bevestigd')->nullable()->comment('null=uitgevraagd, 0=afgewezen, 1=bevestigd');
            $table->text('bericht')->nullable();
            $table->integer('kilometers')->nullable();
            $table->foreignId('ingevoerd_door')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spreekbeurten');
    }
};

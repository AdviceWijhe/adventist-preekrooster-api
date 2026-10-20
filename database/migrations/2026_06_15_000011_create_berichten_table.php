<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('berichten', function (Blueprint $table): void {
            $table->id();
            $table->string('titel');
            $table->text('inhoud');
            $table->foreignId('auteur_id')->constrained('users')->cascadeOnDelete();
            $table->string('doelgroep', 32);
            $table->string('kanaal', 16)->default('intern');
            $table->timestamp('gepubliceerd_op')->nullable();
            $table->timestamps();
        });

        Schema::create('bericht_gelezen', function (Blueprint $table): void {
            $table->foreignId('bericht_id')->constrained('berichten')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('gelezen_op');
            $table->primary(['bericht_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bericht_gelezen');
        Schema::dropIfExists('berichten');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('voornaam');
            $table->string('tussenvoegsel')->nullable();
            $table->string('achternaam');
            $table->string('initialen')->nullable();
            $table->enum('geslacht', ['m', 'v', 'o']);
            $table->string('email')->unique();
            $table->string('password');
            $table->string('taal', 5)->default('nl');
            $table->unsignedBigInteger('gemeente_id')->nullable();
            $table->string('functie')->nullable();
            $table->string('photo')->nullable();
            $table->string('telefoonnummer')->nullable();
            $table->string('mobiel')->nullable();
            $table->boolean('two_factor_enabled')->default(true);
            $table->boolean('active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};

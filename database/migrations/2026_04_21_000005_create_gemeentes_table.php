<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gemeentes', function (Blueprint $table): void {
            $table->id();
            $table->string('naam');
            $table->string('naam_kort')->nullable();
            $table->string('adres')->nullable();
            $table->string('plaats')->nullable();
            $table->string('postcode')->nullable();
            $table->foreignId('district_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('predikant_id')->nullable();
            $table->unsignedBigInteger('contactpersoon_id')->nullable();
            $table->string('begintijd_ochtend', 5)->default('10:00');
            $table->string('begintijd_avond', 5)->default('18:30');
            $table->string('kerk')->nullable();
            $table->string('website_url')->nullable();
            $table->string('livestream_url')->nullable();
            $table->string('taal', 10)->default('nl');
            $table->boolean('active')->default(true);
            $table->boolean('church_plant')->default(false);
            $table->integer('volgorde')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gemeentes');
    }
};

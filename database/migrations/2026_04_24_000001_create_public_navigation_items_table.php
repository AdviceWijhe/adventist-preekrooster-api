<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_navigation_items', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('label_nl');
            $table->string('label_en');
            $table->string('url');
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('visible')->default(true);
            $table->boolean('external')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_navigation_items');
    }
};

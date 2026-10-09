<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publicaties', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gemeente_id')->nullable()->constrained('gemeentes')->nullOnDelete();
            $table->string('periode', 7)->comment('YYYY-MM formaat');
            $table->boolean('gepubliceerd')->default(false);
            $table->timestamp('gepubliceerd_op')->nullable();
            $table->foreignId('gepubliceerd_door')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['gemeente_id', 'periode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publicaties');
    }
};

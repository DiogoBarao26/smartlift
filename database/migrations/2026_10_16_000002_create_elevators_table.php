<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('elevators', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedSmallInteger('current_floor')->default(1);
            $table->string('direction', 10)->default('idle');
            $table->string('status', 30)->default('idle');
            $table->decimal('max_weight_kg', 8, 2);
            $table->unsignedSmallInteger('max_passengers');
            $table->unsignedSmallInteger('min_floor')->default(1);
            $table->unsignedSmallInteger('max_floor')->default(5);
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('elevators');
    }
};

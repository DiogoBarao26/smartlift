<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('people', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('card_code')->unique();
            $table->unsignedSmallInteger('destination_floor');
            $table->decimal('weight_kg', 6, 2);
            $table->string('card_status', 20)->default('active');
            $table->timestamps();

            $table->index('card_status');
            $table->index('destination_floor');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('people');
    }
};

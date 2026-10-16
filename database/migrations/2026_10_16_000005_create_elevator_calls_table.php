<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('elevator_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('elevator_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('requested_floor');
            $table->string('requested_direction', 10)->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('requested_at');
            $table->timestamp('attended_at')->nullable();
            $table->timestamps();

            $table->index(['elevator_id', 'status']);
            $table->index('requested_floor');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('elevator_calls');
    }
};

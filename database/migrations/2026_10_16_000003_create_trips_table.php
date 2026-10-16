<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('elevator_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('origin_floor');
            $table->unsignedSmallInteger('destination_floor');
            $table->string('direction', 10);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->string('status', 20)->default('boarding');
            $table->timestamps();

            $table->index(['elevator_id', 'status']);
            $table->index(['status', 'direction']);
            $table->index('finished_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};

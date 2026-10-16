<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_passengers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->decimal('weight_at_entry', 6, 2);
            $table->unsignedSmallInteger('destination_floor');
            $table->timestamp('boarded_at');
            $table->timestamp('exited_at')->nullable();
            $table->timestamps();

            $table->index(['person_id', 'exited_at']);
            $table->index('trip_id');
            $table->index('destination_floor');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_passengers');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('elevator_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('elevator_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trip_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->string('event_type', 40);
            $table->unsignedSmallInteger('floor')->nullable();
            $table->decimal('weight_kg', 8, 2)->nullable();
            $table->string('description');
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['elevator_id', 'occurred_at']);
            $table->index('event_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('elevator_events');
    }
};

<?php

namespace Tests\Feature;

use App\Enums\ElevatorEventType;
use App\Enums\TripStatus;
use App\Models\ElevatorEvent;
use App\Models\Trip;
use App\Models\TripPassenger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatisticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_statistics_come_from_persisted_records_and_ignore_ride_rows(): void
    {
        $elevator = $this->createElevator();
        $person = $this->createPerson(['weight_kg' => 62, 'destination_floor' => 5]);

        Trip::query()->create([
            'elevator_id' => $elevator->id,
            'origin_floor' => 1,
            'destination_floor' => 5,
            'direction' => 'up',
            'started_at' => now(),
            'finished_at' => now(),
            'status' => TripStatus::Completed,
        ]);

        Trip::query()->create([
            'elevator_id' => $elevator->id,
            'origin_floor' => 5,
            'destination_floor' => 2,
            'direction' => 'down',
            'started_at' => now(),
            'finished_at' => now(),
            'status' => TripStatus::Completed,
        ]);

        $ride = Trip::query()->create([
            'elevator_id' => $elevator->id,
            'origin_floor' => 1,
            'destination_floor' => 5,
            'direction' => 'up',
            'started_at' => now(),
            'finished_at' => now(),
            'status' => TripStatus::Fulfilled,
        ]);

        TripPassenger::query()->create([
            'trip_id' => $ride->id,
            'person_id' => $person->id,
            'weight_at_entry' => 62,
            'destination_floor' => 5,
            'boarded_at' => now(),
            'exited_at' => now(),
        ]);

        ElevatorEvent::query()->create([
            'elevator_id' => $elevator->id,
            'person_id' => $person->id,
            'event_type' => ElevatorEventType::BoardRefused,
            'floor' => 1,
            'description' => 'Embarque recusado no teste.',
            'occurred_at' => now(),
        ]);

        $person->update(['weight_kg' => 10]);

        $this->getJson('/api/statistics')
            ->assertOk()
            ->assertJsonPath('data.trips_total', 2)
            ->assertJsonPath('data.ascents_total', 1)
            ->assertJsonPath('data.descents_total', 1)
            ->assertJsonPath('data.passengers_transported', 1)
            ->assertJsonPath('data.weight_transported_kg', 62)
            ->assertJsonPath('data.average_weight_per_trip_kg', 31)
            ->assertJsonPath('data.refused_boardings', 1);

        $this->getJson('/api/statistics/passengers')
            ->assertOk()
            ->assertJsonPath('data.by_destination_floor.0.floor', 5)
            ->assertJsonPath('data.by_destination_floor.0.passengers', 1);

        $this->getJson('/api/statistics/weight')
            ->assertOk()
            ->assertJsonPath('data.weight_transported_kg', 62)
            ->assertJsonPath('data.average_weight_per_boarding_kg', 62);

        $this->getJson('/api/statistics/daily')
            ->assertOk()
            ->assertJsonPath('data.trips_total', 2)
            ->assertJsonPath('data.passengers_transported', 1);
    }
}

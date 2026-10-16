<?php

namespace Tests\Feature;

use App\Enums\TripStatus;
use App\Models\Elevator;
use App\Models\Trip;
use App\Models\TripPassenger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ElevatorMovementTest extends TestCase
{
    use RefreshDatabase;

    public function test_call_moves_the_elevator_and_records_the_trip(): void
    {
        $this->createElevator(['current_floor' => 1, 'status' => 'idle']);

        $this->postJson('/api/elevator/call', [
            'requested_floor' => 4,
            'requested_direction' => 'up',
        ])->assertOk()
            ->assertJsonPath('data.elevator.current_floor', 4)
            ->assertJsonPath('data.elevator.status', 'doors_open')
            ->assertJsonPath('data.animation.steps.1.from', 1)
            ->assertJsonPath('data.animation.steps.1.to', 4)
            ->assertJsonPath('data.animation.steps.1.direction', 'up');

        $this->assertSame(1, Trip::query()->where('status', TripStatus::Completed)->count());
        $this->assertDatabaseHas('trips', [
            'origin_floor' => 1,
            'destination_floor' => 4,
            'direction' => 'up',
            'status' => TripStatus::Completed->value,
        ]);
        $this->assertDatabaseHas('elevator_calls', [
            'requested_floor' => 4,
            'status' => 'attended',
        ]);
    }

    public function test_calling_the_current_floor_opens_the_doors_without_a_trip(): void
    {
        $this->createElevator(['current_floor' => 2, 'status' => 'idle']);

        $this->postJson('/api/elevator/call', ['requested_floor' => 2])
            ->assertOk()
            ->assertJsonPath('data.elevator.current_floor', 2)
            ->assertJsonPath('data.elevator.door_open', true);

        $this->assertSame(0, Trip::query()->where('status', TripStatus::Completed)->count());
    }

    public function test_elevator_serves_the_current_direction_before_reversing(): void
    {
        $this->createElevator(['status' => 'doors_open', 'current_floor' => 1]);
        $passenger = $this->createPerson(['card_code' => 'DIR-3001', 'destination_floor' => 3, 'weight_kg' => 60]);

        $this->postJson('/api/elevator/board', ['card_code' => $passenger->card_code])->assertOk();
        $this->postJson('/api/elevator/call', ['requested_floor' => 5])
            ->assertOk()
            ->assertJsonPath('data.elevator.current_floor', 1)
            ->assertJsonPath('data.queued', true);

        $this->postJson('/api/elevator/depart')
            ->assertOk()
            ->assertJsonPath('data.elevator.current_floor', 3);

        $this->postJson('/api/elevator/alight', ['person_id' => $passenger->id])->assertOk();

        $this->postJson('/api/elevator/depart')
            ->assertOk()
            ->assertJsonPath('data.elevator.current_floor', 5);

        $this->assertSame(2, Trip::query()->where('status', TripStatus::Completed)->where('direction', 'up')->count());
        $this->assertSame(0, Trip::query()->where('status', TripStatus::Completed)->where('direction', 'down')->count());
    }

    public function test_trip_does_not_start_when_the_car_is_over_capacity(): void
    {
        $elevator = $this->createElevator(['status' => 'idle', 'current_floor' => 1, 'max_weight_kg' => 100]);
        $person = $this->createPerson(['weight_kg' => 150, 'destination_floor' => 4]);

        $trip = \App\Models\Trip::query()->create([
            'elevator_id' => $elevator->id,
            'origin_floor' => 1,
            'destination_floor' => 4,
            'direction' => 'up',
            'started_at' => now(),
            'status' => TripStatus::Boarding,
        ]);

        TripPassenger::query()->create([
            'trip_id' => $trip->id,
            'person_id' => $person->id,
            'weight_at_entry' => 150,
            'destination_floor' => 4,
            'boarded_at' => now(),
        ]);

        $this->postJson('/api/elevator/depart')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Viagem não iniciada: capacidade máxima de peso excedida.');

        $this->assertSame(1, Elevator::query()->first()->current_floor);
        $this->assertSame(0, Trip::query()->where('status', TripStatus::Completed)->count());
    }
}

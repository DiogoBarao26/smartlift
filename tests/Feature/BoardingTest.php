<?php

namespace Tests\Feature;

use App\Enums\ElevatorEventType;
use App\Models\ElevatorEvent;
use App\Models\TripPassenger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_boarding_is_refused_when_doors_are_closed(): void
    {
        $this->createElevator();
        $person = $this->createPerson();

        $this->postJson('/api/elevator/board', ['card_code' => $person->card_code])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Embarque não autorizado: as portas do elevador estão fechadas.');
    }

    public function test_successful_boarding_updates_weight_and_count(): void
    {
        $this->createElevator(['status' => 'doors_open']);
        $person = $this->createPerson(['weight_kg' => 82.5, 'destination_floor' => 5]);

        $response = $this->postJson('/api/elevator/board', ['card_code' => $person->card_code]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.passenger_count', 1)
            ->assertJsonPath('data.current_weight_kg', 82.5)
            ->assertJsonPath('data.passengers.0.name', $person->name)
            ->assertJsonPath('data.elevator.status', 'doors_open');

        $this->assertDatabaseHas('trip_passengers', [
            'person_id' => $person->id,
            'weight_at_entry' => 82.5,
            'destination_floor' => 5,
        ]);
    }

    public function test_boarding_is_refused_when_weight_limit_is_exceeded(): void
    {
        $this->createElevator(['status' => 'doors_open', 'max_weight_kg' => 100]);
        $this->createPerson(['name' => 'Primeiro', 'card_code' => 'PRI-1001', 'weight_kg' => 80, 'destination_floor' => 3]);
        $second = $this->createPerson(['name' => 'Segundo', 'card_code' => 'SEG-1002', 'weight_kg' => 30, 'destination_floor' => 4]);

        $this->postJson('/api/elevator/board', ['card_code' => 'PRI-1001'])->assertOk();

        $this->postJson('/api/elevator/board', ['card_code' => $second->card_code])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Embarque não autorizado: capacidade máxima de peso excedida.');

        $this->assertSame(1, TripPassenger::query()->whereNull('exited_at')->count());
        $this->assertDatabaseHas('elevator_events', [
            'event_type' => ElevatorEventType::BoardRefused->value,
            'person_id' => $second->id,
        ]);
    }

    public function test_boarding_is_refused_when_passenger_limit_is_reached(): void
    {
        $this->createElevator(['status' => 'doors_open', 'max_passengers' => 1]);
        $this->createPerson(['card_code' => 'PRI-2001', 'weight_kg' => 60, 'destination_floor' => 2]);
        $this->createPerson(['card_code' => 'SEG-2002', 'weight_kg' => 60, 'destination_floor' => 3]);

        $this->postJson('/api/elevator/board', ['card_code' => 'PRI-2001'])->assertOk();

        $this->postJson('/api/elevator/board', ['card_code' => 'SEG-2002'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Embarque não autorizado: capacidade máxima de passageiros atingida.');
    }

    public function test_invalid_card_is_refused(): void
    {
        $this->createElevator(['status' => 'doors_open']);

        $this->postJson('/api/elevator/board', ['card_code' => 'NAO-EXISTE'])
            ->assertNotFound()
            ->assertJsonPath('message', 'Cartão não encontrado.');

        $this->assertSame(1, ElevatorEvent::query()->where('event_type', ElevatorEventType::BoardRefused)->count());
    }

    public function test_blocked_card_is_refused(): void
    {
        $this->createElevator(['status' => 'doors_open']);
        $person = $this->createPerson(['card_status' => 'blocked']);

        $this->postJson('/api/elevator/board', ['card_code' => $person->card_code])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Embarque não autorizado: cartão bloqueado.');

        $this->assertSame(0, TripPassenger::query()->count());
    }

    public function test_the_same_person_cannot_board_twice(): void
    {
        $this->createElevator(['status' => 'doors_open']);
        $person = $this->createPerson();

        $this->postJson('/api/elevator/board', ['card_code' => $person->card_code])->assertOk();

        $this->postJson('/api/elevator/board', ['card_code' => $person->card_code])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Embarque não autorizado: esta pessoa já está no elevador.');

        $this->assertSame(1, TripPassenger::query()->count());
    }

    public function test_card_without_floor_authorization_is_refused(): void
    {
        $this->createElevator(['status' => 'doors_open', 'max_floor' => 5]);
        $person = $this->createPerson(['destination_floor' => 9]);

        $this->postJson('/api/elevator/board', ['card_code' => $person->card_code])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Embarque não autorizado: cartão sem autorização para o andar de destino.');
    }

    public function test_passenger_can_alight_only_at_the_authorized_floor(): void
    {
        $this->createElevator(['status' => 'doors_open', 'current_floor' => 1]);
        $person = $this->createPerson(['destination_floor' => 4, 'weight_kg' => 70]);

        $this->postJson('/api/elevator/board', ['card_code' => $person->card_code])->assertOk();

        $this->postJson('/api/elevator/alight', ['person_id' => $person->id])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Desembarque não autorizado: o passageiro só pode sair no andar 4.');

        $this->assertNull(TripPassenger::query()->first()->exited_at);
    }

    public function test_alighting_updates_load_and_history(): void
    {
        $this->createElevator(['status' => 'doors_open', 'current_floor' => 1]);
        $person = $this->createPerson(['destination_floor' => 4, 'weight_kg' => 70]);

        $this->postJson('/api/elevator/board', ['card_code' => $person->card_code])->assertOk();

        $this->postJson('/api/elevator/depart')
            ->assertOk()
            ->assertJsonPath('data.elevator.current_floor', 4)
            ->assertJsonPath('data.elevator.door_open', true);

        $this->postJson('/api/elevator/alight', ['person_id' => $person->id])
            ->assertOk()
            ->assertJsonPath('data.passenger_count', 0)
            ->assertJsonPath('data.current_weight_kg', 0);

        $this->assertNotNull(TripPassenger::query()->first()->exited_at);
        $this->assertDatabaseHas('elevator_events', [
            'event_type' => ElevatorEventType::Alighted->value,
            'person_id' => $person->id,
            'floor' => 4,
        ]);
    }
}

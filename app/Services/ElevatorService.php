<?php

namespace App\Services;

use App\Enums\CallStatus;
use App\Enums\CardStatus;
use App\Enums\ElevatorDirection;
use App\Enums\ElevatorEventType;
use App\Enums\ElevatorStatus;
use App\Enums\TripStatus;
use App\Exceptions\ElevatorException;
use App\Models\Elevator;
use App\Models\ElevatorCall;
use App\Models\ElevatorEvent;
use App\Models\Person;
use App\Models\Trip;
use App\Models\TripPassenger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ElevatorService
{
    public function state(): array
    {
        return $this->present($this->elevator(), 'Estado atual do elevador.');
    }

    public function call(int $floor, ?string $direction): array
    {
        return DB::transaction(function () use ($floor, $direction) {
            $elevator = $this->lockedElevator();
            $this->assertFloorInBuilding($elevator, $floor);

            $requestedDirection = $direction ?: ((int) $floor < (int) $elevator->max_floor ? 'up' : 'down');

            $existing = ElevatorCall::query()
                ->where('elevator_id', $elevator->id)
                ->where('requested_floor', $floor)
                ->where('status', CallStatus::Pending)
                ->first();

            if ($existing === null) {
                ElevatorCall::query()->create([
                    'elevator_id' => $elevator->id,
                    'requested_floor' => $floor,
                    'requested_direction' => $requestedDirection,
                    'status' => CallStatus::Pending,
                    'requested_at' => now(),
                ]);

                $this->logEvent(
                    $elevator->id,
                    ElevatorEventType::CallRequested,
                    "Chamada registrada para o andar {$floor}.",
                    $floor,
                );
            }

            if ((int) $elevator->current_floor === $floor && $elevator->status === ElevatorStatus::DoorsOpen) {
                $this->attendCalls($elevator, $floor);

                return $this->present($elevator->fresh(), 'O elevador já está neste andar com as portas abertas.');
            }

            if ((int) $elevator->current_floor === $floor) {
                $this->attendCalls($elevator, $floor);
                $elevator->status = ElevatorStatus::DoorsOpen;
                $elevator->direction = ElevatorDirection::Idle;
                $elevator->save();

                $this->logEvent(
                    $elevator->id,
                    ElevatorEventType::DoorsOpened,
                    "Portas abertas no andar {$floor}.",
                    $floor,
                );

                return $this->present($elevator, 'O elevador já está no andar solicitado. Portas abertas.', [
                    'steps' => [
                        ['type' => 'open_doors', 'duration_ms' => 700],
                    ],
                ]);
            }

            if ($elevator->status === ElevatorStatus::DoorsOpen) {
                return $this->present(
                    $elevator,
                    'Chamada registrada. O elevador atenderá ao fechar as portas.',
                    null,
                    ['queued' => true],
                );
            }

            return $this->travelToNextStop($elevator, 'Chamada registrada. O elevador está a caminho.');
        });
    }

    public function board(string $cardCode): array
    {
        $refusal = null;

        try {
            return DB::transaction(function () use ($cardCode, &$refusal) {
                $elevator = $this->lockedElevator();
                $code = strtoupper(trim($cardCode));
                $person = Person::query()->where('card_code', $code)->lockForUpdate()->first();
                $passengers = $this->onboardPassengers($elevator);
                $floor = (int) $elevator->current_floor;

                $deny = function (string $message, int $status = 422) use (&$refusal, $elevator, $person, $floor): void {
                    $refusal = [
                        'elevator_id' => $elevator->id,
                        'person_id' => $person?->id,
                        'floor' => $floor,
                        'weight' => $person ? (float) $person->weight_kg : null,
                        'description' => $message,
                    ];

                    throw new ElevatorException($message, $status);
                };

                if ($elevator->status !== ElevatorStatus::DoorsOpen) {
                    $deny('Embarque não autorizado: as portas do elevador estão fechadas.');
                }

                if ($person === null) {
                    $deny('Cartão não encontrado.', 404);
                }

                if ($person->card_status !== CardStatus::Active) {
                    $deny('Embarque não autorizado: cartão bloqueado.');
                }

                if ((int) $person->destination_floor < (int) $elevator->min_floor
                    || (int) $person->destination_floor > (int) $elevator->max_floor) {
                    $deny('Embarque não autorizado: cartão sem autorização para o andar de destino.');
                }

                if ((int) $person->destination_floor === $floor) {
                    $deny('Embarque não autorizado: o passageiro já se encontra no andar de destino.');
                }

                if ($passengers->contains(fn (TripPassenger $passenger) => (int) $passenger->person_id === (int) $person->id)) {
                    $deny('Embarque não autorizado: esta pessoa já está no elevador.');
                }

                if ($passengers->count() + 1 > (int) $elevator->max_passengers) {
                    $deny('Embarque não autorizado: capacidade máxima de passageiros atingida.');
                }

                $nextWeight = round($this->weightOf($passengers) + (float) $person->weight_kg, 2);

                if ($nextWeight > round((float) $elevator->max_weight_kg, 2)) {
                    $deny('Embarque não autorizado: capacidade máxima de peso excedida.');
                }

                $trip = Trip::query()->create([
                    'elevator_id' => $elevator->id,
                    'origin_floor' => $floor,
                    'destination_floor' => $person->destination_floor,
                    'direction' => (int) $person->destination_floor > $floor ? 'up' : 'down',
                    'started_at' => now(),
                    'status' => TripStatus::Boarding,
                ]);

                TripPassenger::query()->create([
                    'trip_id' => $trip->id,
                    'person_id' => $person->id,
                    'weight_at_entry' => $person->weight_kg,
                    'destination_floor' => $person->destination_floor,
                    'boarded_at' => now(),
                ]);

                $this->logEvent(
                    $elevator->id,
                    ElevatorEventType::BoardAuthorized,
                    "{$person->name} embarcou com destino ao andar {$person->destination_floor}.",
                    $floor,
                    $person->id,
                    $trip->id,
                    (float) $person->weight_kg,
                );

                return $this->present(
                    $elevator,
                    "Embarque autorizado para {$person->name}.",
                    null,
                    [
                        'should_depart' => true,
                        'boarded_passenger' => [
                            'id' => $person->id,
                            'name' => $person->name,
                            'destination_floor' => (int) $person->destination_floor,
                            'weight_kg' => (float) $person->weight_kg,
                            'card_code' => $person->card_code,
                        ],
                    ],
                );
            });
        } catch (ElevatorException $exception) {
            if ($refusal !== null) {
                $this->logEvent(
                    $refusal['elevator_id'],
                    ElevatorEventType::BoardRefused,
                    $refusal['description'],
                    $refusal['floor'],
                    $refusal['person_id'],
                    null,
                    $refusal['weight'],
                );
            }

            throw $exception;
        }
    }

    public function alight(?int $personId): array
    {
        return DB::transaction(function () use ($personId) {
            $elevator = $this->lockedElevator();

            if ($elevator->status !== ElevatorStatus::DoorsOpen) {
                throw new ElevatorException('Desembarque não autorizado: as portas do elevador estão fechadas.');
            }

            $passengers = $this->onboardPassengers($elevator);

            if ($personId !== null) {
                $selected = $passengers->firstWhere('person_id', $personId);

                if ($selected === null) {
                    throw new ElevatorException('Este passageiro não está no elevador.');
                }

                if ((int) $selected->destination_floor !== (int) $elevator->current_floor) {
                    throw new ElevatorException(
                        "Desembarque não autorizado: o passageiro só pode sair no andar {$selected->destination_floor}."
                    );
                }

                $leaving = collect([$selected]);
            } else {
                $leaving = $passengers
                    ->filter(fn (TripPassenger $passenger) => (int) $passenger->destination_floor === (int) $elevator->current_floor)
                    ->values();

                if ($leaving->isEmpty()) {
                    throw new ElevatorException('Nenhum passageiro está autorizado a desembarcar neste andar.');
                }
            }

            foreach ($leaving as $passenger) {
                $passenger->exited_at = now();
                $passenger->save();
                $passenger->trip->update([
                    'status' => TripStatus::Fulfilled,
                    'finished_at' => now(),
                ]);

                $this->logEvent(
                    $elevator->id,
                    ElevatorEventType::Alighted,
                    "{$passenger->person->name} desembarcou no andar {$elevator->current_floor}.",
                    (int) $elevator->current_floor,
                    $passenger->person_id,
                    $passenger->trip_id,
                    (float) $passenger->weight_at_entry,
                );
            }

            $names = $leaving->map(fn (TripPassenger $passenger) => $passenger->person->name)->implode(', ');

            return $this->present($elevator, "Desembarque registrado: {$names}.");
        });
    }

    public function depart(): array
    {
        return DB::transaction(function () {
            $elevator = $this->lockedElevator();

            return $this->travelToNextStop($elevator, 'Deslocamento concluído.');
        });
    }

    public function updateSettings(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $elevator = $this->lockedElevator();
            $passengers = $this->onboardPassengers($elevator);
            $weight = $this->weightOf($passengers);
            $min = (int) $data['min_floor'];
            $max = (int) $data['max_floor'];

            if ((int) $elevator->current_floor < $min || (int) $elevator->current_floor > $max) {
                throw new ElevatorException('A faixa de andares não pode excluir o andar atual do elevador.');
            }

            if ($passengers->count() > (int) $data['max_passengers']) {
                throw new ElevatorException('A capacidade de passageiros não pode ser menor que a ocupação atual.');
            }

            if (round((float) $data['max_weight_kg'], 2) < $weight) {
                throw new ElevatorException('A capacidade de peso não pode ser menor que a carga atual.');
            }

            $outside = Person::query()
                ->where('card_status', CardStatus::Active)
                ->where(function ($query) use ($min, $max) {
                    $query->where('destination_floor', '<', $min)
                        ->orWhere('destination_floor', '>', $max);
                })
                ->exists();

            if ($outside) {
                throw new ElevatorException('Existem cartões ativos com destino fora da nova faixa de andares.');
            }

            if ($passengers->contains(fn (TripPassenger $passenger) => (int) $passenger->destination_floor < $min || (int) $passenger->destination_floor > $max)) {
                throw new ElevatorException('Há passageiros a bordo com destino fora da nova faixa de andares.');
            }

            $elevator->fill([
                'name' => $data['name'] ?? $elevator->name,
                'min_floor' => $min,
                'max_floor' => $max,
                'max_weight_kg' => $data['max_weight_kg'],
                'max_passengers' => $data['max_passengers'],
            ]);
            $elevator->save();

            $this->logEvent(
                $elevator->id,
                ElevatorEventType::ConfigUpdated,
                'Configuração do elevador atualizada.',
                (int) $elevator->current_floor,
                null,
                null,
                (float) $elevator->max_weight_kg,
            );

            return $this->present($elevator, 'Configuração atualizada.');
        });
    }

    public function history(int $perPage = 20)
    {
        $perPage = min(max($perPage, 1), 100);

        return ElevatorEvent::query()
            ->with('person')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    private function travelToNextStop(Elevator $elevator, string $message): array
    {
        $passengers = $this->onboardPassengers($elevator);
        $this->assertCanTravel($passengers, $elevator);

        $from = (int) $elevator->current_floor;
        $next = $this->nextStop($elevator, $passengers);
        $doorsWereOpen = $elevator->status === ElevatorStatus::DoorsOpen;

        if ($next === null) {
            if ($doorsWereOpen) {
                $elevator->status = ElevatorStatus::Idle;
                $elevator->direction = ElevatorDirection::Idle;
                $elevator->save();

                $this->logEvent(
                    $elevator->id,
                    ElevatorEventType::DoorsClosed,
                    "Portas fechadas no andar {$from}.",
                    $from,
                    null,
                    null,
                    $this->weightOf($passengers),
                );

                return $this->present($elevator, 'Não há mais chamadas. Portas fechadas.', [
                    'steps' => [
                        ['type' => 'close_doors', 'duration_ms' => 700],
                    ],
                ]);
            }

            return $this->present($elevator, 'Não há chamadas nem destinos pendentes.');
        }

        $direction = $next > $from ? ElevatorDirection::Up : ElevatorDirection::Down;
        $floors = abs($next - $from);
        $moveMs = $floors * 900;
        $closeMs = $doorsWereOpen ? 700 : 250;
        $load = $this->weightOf($passengers);

        if ($doorsWereOpen) {
            $this->logEvent(
                $elevator->id,
                ElevatorEventType::DoorsClosed,
                "Portas fechadas no andar {$from}.",
                $from,
                null,
                null,
                $load,
            );
        }

        $trip = Trip::query()->create([
            'elevator_id' => $elevator->id,
            'origin_floor' => $from,
            'destination_floor' => $next,
            'direction' => $direction->value,
            'started_at' => now(),
            'finished_at' => now(),
            'status' => TripStatus::Completed,
        ]);

        $this->logEvent(
            $elevator->id,
            ElevatorEventType::MoveStarted,
            "Elevador partiu do andar {$from} em direção ao andar {$next}.",
            $from,
            null,
            $trip->id,
            $load,
        );

        $elevator->current_floor = $next;
        $elevator->direction = $direction;
        $elevator->status = ElevatorStatus::DoorsOpen;
        $elevator->save();

        $this->logEvent(
            $elevator->id,
            ElevatorEventType::Arrived,
            "Elevador chegou ao andar {$next}.",
            $next,
            null,
            $trip->id,
            $load,
        );

        $this->attendCalls($elevator, $next);

        $this->logEvent(
            $elevator->id,
            ElevatorEventType::DoorsOpened,
            "Portas abertas no andar {$next}.",
            $next,
            null,
            $trip->id,
            $load,
        );

        return $this->present($elevator, $message, [
            'steps' => [
                ['type' => 'close_doors', 'duration_ms' => $closeMs],
                [
                    'type' => 'move',
                    'from' => $from,
                    'to' => $next,
                    'direction' => $direction->value,
                    'duration_ms' => $moveMs,
                ],
                ['type' => 'open_doors', 'duration_ms' => 700],
            ],
        ], [
            'trip' => [
                'id' => $trip->id,
                'origin_floor' => $from,
                'destination_floor' => $next,
                'direction' => $direction->value,
                'status' => TripStatus::Completed->value,
            ],
        ]);
    }

    private function assertCanTravel(Collection $passengers, Elevator $elevator): void
    {
        if ($passengers->count() > (int) $elevator->max_passengers) {
            throw new ElevatorException('Viagem não iniciada: capacidade máxima de passageiros excedida.');
        }

        if ($this->weightOf($passengers) > round((float) $elevator->max_weight_kg, 2)) {
            throw new ElevatorException('Viagem não iniciada: capacidade máxima de peso excedida.');
        }
    }

    private function nextStop(Elevator $elevator, Collection $passengers): ?int
    {
        $current = (int) $elevator->current_floor;

        $targets = collect($passengers->pluck('destination_floor'))
            ->merge(
                ElevatorCall::query()
                    ->where('elevator_id', $elevator->id)
                    ->where('status', CallStatus::Pending)
                    ->pluck('requested_floor')
            )
            ->map(fn ($floor) => (int) $floor)
            ->unique()
            ->filter(fn (int $floor) => $floor !== $current)
            ->values()
            ->all();

        if ($targets === []) {
            return null;
        }

        $above = array_values(array_filter($targets, fn (int $floor) => $floor > $current));
        $below = array_values(array_filter($targets, fn (int $floor) => $floor < $current));
        sort($above);
        rsort($below);

        if ($elevator->direction === ElevatorDirection::Down) {
            return $below[0] ?? $above[0] ?? null;
        }

        return $above[0] ?? $below[0] ?? null;
    }

    private function attendCalls(Elevator $elevator, int $floor): int
    {
        $calls = ElevatorCall::query()
            ->where('elevator_id', $elevator->id)
            ->where('requested_floor', $floor)
            ->where('status', CallStatus::Pending)
            ->lockForUpdate()
            ->get();

        foreach ($calls as $call) {
            $call->status = CallStatus::Attended;
            $call->attended_at = now();
            $call->save();

            $this->logEvent(
                $elevator->id,
                ElevatorEventType::CallAttended,
                "Chamada do andar {$floor} atendida.",
                $floor,
            );
        }

        return $calls->count();
    }

    private function present(Elevator $elevator, string $message, ?array $animation = null, array $extra = []): array
    {
        $elevator->refresh();
        $passengers = $this->onboardPassengers($elevator);
        $weight = $this->weightOf($passengers);
        $count = $passengers->count();
        $maxWeight = (float) $elevator->max_weight_kg;
        $maxPassengers = (int) $elevator->max_passengers;
        $doorsOpen = $elevator->status === ElevatorStatus::DoorsOpen;

        $mapped = $passengers->map(fn (TripPassenger $passenger) => [
            'trip_passenger_id' => $passenger->id,
            'person_id' => $passenger->person_id,
            'name' => $passenger->person->name,
            'card_code' => $passenger->person->card_code,
            'destination_floor' => (int) $passenger->destination_floor,
            'weight_kg' => (float) $passenger->weight_at_entry,
            'boarded_at' => $passenger->boarded_at?->toIso8601String(),
        ])->values();

        $ready = $doorsOpen
            ? $mapped->filter(fn (array $passenger) => $passenger['destination_floor'] === (int) $elevator->current_floor)->values()
            : collect();

        $pendingCalls = ElevatorCall::query()
            ->where('elevator_id', $elevator->id)
            ->where('status', CallStatus::Pending)
            ->orderBy('requested_floor')
            ->get()
            ->map(fn (ElevatorCall $call) => [
                'id' => $call->id,
                'requested_floor' => (int) $call->requested_floor,
                'requested_direction' => $call->requested_direction,
                'requested_at' => $call->requested_at?->toIso8601String(),
            ])
            ->values();

        $data = [
            'elevator' => [
                'id' => $elevator->id,
                'name' => $elevator->name,
                'current_floor' => (int) $elevator->current_floor,
                'min_floor' => (int) $elevator->min_floor,
                'max_floor' => (int) $elevator->max_floor,
                'floors' => range((int) $elevator->min_floor, (int) $elevator->max_floor),
                'direction' => $elevator->direction->value,
                'direction_label' => $elevator->direction->label(),
                'status' => $elevator->status->value,
                'status_label' => $elevator->status->label(),
                'max_weight_kg' => $maxWeight,
                'max_passengers' => $maxPassengers,
                'door_open' => $doorsOpen,
            ],
            'passengers' => $mapped,
            'passenger_count' => $count,
            'current_weight_kg' => $weight,
            'capacity' => [
                'weight_percent' => $maxWeight > 0 ? round(($weight / $maxWeight) * 100, 1) : 0,
                'passenger_percent' => $maxPassengers > 0 ? round(($count / $maxPassengers) * 100, 1) : 0,
                'weight_remaining_kg' => round(max($maxWeight - $weight, 0), 2),
                'seats_remaining' => max($maxPassengers - $count, 0),
            ],
            'pending_calls' => $pendingCalls,
            'ready_to_alight' => $ready,
            'pending_work' => $this->nextStop($elevator, $passengers) !== null,
            'animation' => $animation,
        ];

        return [
            'message' => $message,
            'data' => array_merge($data, $extra),
        ];
    }

    private function onboardPassengers(Elevator $elevator): Collection
    {
        return TripPassenger::query()
            ->whereNull('exited_at')
            ->whereHas('trip', fn ($query) => $query->where('elevator_id', $elevator->id))
            ->with(['person', 'trip'])
            ->lockForUpdate()
            ->get()
            ->sortBy('boarded_at')
            ->values();
    }

    private function weightOf(Collection $passengers): float
    {
        return round($passengers->sum(fn (TripPassenger $passenger) => (float) $passenger->weight_at_entry), 2);
    }

    private function assertFloorInBuilding(Elevator $elevator, int $floor): void
    {
        if ($floor < (int) $elevator->min_floor || $floor > (int) $elevator->max_floor) {
            throw new ElevatorException('O andar informado não existe neste prédio.');
        }
    }

    private function elevator(): Elevator
    {
        $elevator = Elevator::query()->orderBy('id')->first();

        if ($elevator === null) {
            throw new ElevatorException('Nenhum elevador configurado.', 503);
        }

        return $elevator;
    }

    private function lockedElevator(): Elevator
    {
        $elevator = Elevator::query()->orderBy('id')->lockForUpdate()->first();

        if ($elevator === null) {
            throw new ElevatorException('Nenhum elevador configurado.', 503);
        }

        return $elevator;
    }

    private function logEvent(
        int $elevatorId,
        ElevatorEventType $type,
        string $description,
        ?int $floor = null,
        ?int $personId = null,
        ?int $tripId = null,
        ?float $weight = null,
    ): void {
        ElevatorEvent::query()->create([
            'elevator_id' => $elevatorId,
            'trip_id' => $tripId,
            'person_id' => $personId,
            'event_type' => $type,
            'floor' => $floor,
            'weight_kg' => $weight,
            'description' => $description,
            'occurred_at' => now(),
        ]);
    }
}

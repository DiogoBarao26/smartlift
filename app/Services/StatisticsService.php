<?php

namespace App\Services;

use App\Enums\CallStatus;
use App\Enums\ElevatorEventType;
use App\Enums\TripStatus;
use App\Models\ElevatorCall;
use App\Models\ElevatorEvent;
use App\Models\Trip;
use App\Models\TripPassenger;
use Illuminate\Support\Carbon;

class StatisticsService
{
    public function overview(?string $from = null, ?string $to = null): array
    {
        $trips = Trip::query()->where('status', TripStatus::Completed);
        $passengers = TripPassenger::query();
        $refused = ElevatorEvent::query()->where('event_type', ElevatorEventType::BoardRefused);
        $calls = ElevatorCall::query();

        if ($from) {
            $trips->whereDate('finished_at', '>=', $from);
            $passengers->whereDate('boarded_at', '>=', $from);
            $refused->whereDate('occurred_at', '>=', $from);
            $calls->whereDate('requested_at', '>=', $from);
        }

        if ($to) {
            $trips->whereDate('finished_at', '<=', $to);
            $passengers->whereDate('boarded_at', '<=', $to);
            $refused->whereDate('occurred_at', '<=', $to);
            $calls->whereDate('requested_at', '<=', $to);
        }

        $tripCount = (clone $trips)->count();
        $ascents = (clone $trips)->where('direction', 'up')->count();
        $descents = (clone $trips)->where('direction', 'down')->count();
        $people = (clone $passengers)->count();
        $weight = round((float) (clone $passengers)->sum('weight_at_entry'), 2);

        $history = (clone $trips)
            ->orderByDesc('finished_at')
            ->limit(30)
            ->get()
            ->map(fn (Trip $trip) => [
                'id' => $trip->id,
                'origin_floor' => (int) $trip->origin_floor,
                'destination_floor' => (int) $trip->destination_floor,
                'direction' => $trip->direction,
                'finished_at' => $trip->finished_at?->toIso8601String(),
            ])
            ->values();

        return [
            'period' => [
                'from' => $from,
                'to' => $to,
            ],
            'trips_total' => $tripCount,
            'ascents_total' => $ascents,
            'descents_total' => $descents,
            'passengers_transported' => $people,
            'weight_transported_kg' => $weight,
            'weight_definition' => 'Soma dos pesos registrados no momento de cada embarque. Não representa a carga simultânea do elevador.',
            'average_weight_per_trip_kg' => $tripCount > 0 ? round($weight / $tripCount, 2) : 0,
            'average_weight_per_boarding_kg' => $people > 0 ? round($weight / $people, 2) : 0,
            'refused_boardings' => (clone $refused)->count(),
            'calls_attended' => (clone $calls)->where('status', CallStatus::Attended)->count(),
            'calls_pending' => (clone $calls)->where('status', CallStatus::Pending)->count(),
            'trip_history' => $history,
        ];
    }

    public function daily(?string $date = null): array
    {
        $day = $date ? Carbon::parse($date)->toDateString() : now()->toDateString();

        $trips = Trip::query()
            ->where('status', TripStatus::Completed)
            ->whereDate('finished_at', $day);

        $passengers = TripPassenger::query()->whereDate('boarded_at', $day);
        $tripCount = (clone $trips)->count();
        $weight = round((float) (clone $passengers)->sum('weight_at_entry'), 2);

        return [
            'date' => $day,
            'trips_total' => $tripCount,
            'ascents_total' => (clone $trips)->where('direction', 'up')->count(),
            'descents_total' => (clone $trips)->where('direction', 'down')->count(),
            'passengers_transported' => (clone $passengers)->count(),
            'weight_transported_kg' => $weight,
            'refused_boardings' => ElevatorEvent::query()
                ->where('event_type', ElevatorEventType::BoardRefused)
                ->whereDate('occurred_at', $day)
                ->count(),
            'calls_attended' => ElevatorCall::query()
                ->where('status', CallStatus::Attended)
                ->whereDate('attended_at', $day)
                ->count(),
        ];
    }

    public function passengers(?string $from = null, ?string $to = null): array
    {
        $query = TripPassenger::query();

        if ($from) {
            $query->whereDate('boarded_at', '>=', $from);
        }

        if ($to) {
            $query->whereDate('boarded_at', '<=', $to);
        }

        $byFloor = (clone $query)
            ->selectRaw('destination_floor, COUNT(*) as total, SUM(weight_at_entry) as weight')
            ->groupBy('destination_floor')
            ->orderBy('destination_floor')
            ->get()
            ->map(fn ($row) => [
                'floor' => (int) $row->destination_floor,
                'passengers' => (int) $row->total,
                'weight_kg' => round((float) $row->weight, 2),
            ])
            ->values();

        $recent = (clone $query)
            ->with('person')
            ->orderByDesc('boarded_at')
            ->limit(15)
            ->get()
            ->map(fn (TripPassenger $passenger) => [
                'person_id' => $passenger->person_id,
                'name' => $passenger->person?->name,
                'destination_floor' => (int) $passenger->destination_floor,
                'weight_at_entry' => (float) $passenger->weight_at_entry,
                'boarded_at' => $passenger->boarded_at?->toIso8601String(),
                'exited_at' => $passenger->exited_at?->toIso8601String(),
            ])
            ->values();

        return [
            'passengers_transported' => (clone $query)->count(),
            'by_destination_floor' => $byFloor,
            'recent' => $recent,
        ];
    }

    public function weight(?string $from = null, ?string $to = null): array
    {
        $passengers = TripPassenger::query();
        $trips = Trip::query()->where('status', TripStatus::Completed);

        if ($from) {
            $passengers->whereDate('boarded_at', '>=', $from);
            $trips->whereDate('finished_at', '>=', $from);
        }

        if ($to) {
            $passengers->whereDate('boarded_at', '<=', $to);
            $trips->whereDate('finished_at', '<=', $to);
        }

        $people = (clone $passengers)->count();
        $tripCount = (clone $trips)->count();
        $total = round((float) (clone $passengers)->sum('weight_at_entry'), 2);

        $byDay = (clone $passengers)
            ->selectRaw('DATE(boarded_at) as day, COUNT(*) as boardings, SUM(weight_at_entry) as weight')
            ->groupByRaw('DATE(boarded_at)')
            ->orderBy('day')
            ->get()
            ->map(fn ($row) => [
                'date' => (string) $row->day,
                'boardings' => (int) $row->boardings,
                'weight_kg' => round((float) $row->weight, 2),
            ])
            ->values();

        return [
            'weight_transported_kg' => $total,
            'weight_definition' => 'Soma dos pesos registrados no momento de cada embarque. Não representa a carga simultânea do elevador.',
            'average_weight_per_boarding_kg' => $people > 0 ? round($total / $people, 2) : 0,
            'average_weight_per_trip_kg' => $tripCount > 0 ? round($total / $tripCount, 2) : 0,
            'boardings' => $people,
            'trips' => $tripCount,
            'by_day' => $byDay,
        ];
    }
}

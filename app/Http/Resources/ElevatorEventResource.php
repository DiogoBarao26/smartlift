<?php

namespace App\Http\Resources;

use App\Enums\ElevatorEventType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ElevatorEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'elevator_id' => $this->elevator_id,
            'trip_id' => $this->trip_id,
            'person_id' => $this->person_id,
            'person_name' => $this->person?->name,
            'event_type' => $this->event_type instanceof ElevatorEventType ? $this->event_type->value : $this->event_type,
            'floor' => $this->floor !== null ? (int) $this->floor : null,
            'weight_kg' => $this->weight_kg !== null ? (float) $this->weight_kg : null,
            'description' => $this->description,
            'occurred_at' => $this->occurred_at?->toIso8601String(),
        ];
    }
}

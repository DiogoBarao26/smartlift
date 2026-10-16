<?php

namespace App\Http\Resources;

use App\Enums\CardStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PersonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'card_code' => $this->card_code,
            'destination_floor' => (int) $this->destination_floor,
            'weight_kg' => (float) $this->weight_kg,
            'card_status' => $this->card_status instanceof CardStatus ? $this->card_status->value : $this->card_status,
            'card_status_label' => $this->card_status instanceof CardStatus ? $this->card_status->label() : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

<?php

namespace App\Models;

use App\Enums\ElevatorEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ElevatorEvent extends Model
{
    protected $fillable = [
        'elevator_id',
        'trip_id',
        'person_id',
        'event_type',
        'floor',
        'weight_kg',
        'description',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => ElevatorEventType::class,
            'floor' => 'integer',
            'weight_kg' => 'decimal:2',
            'occurred_at' => 'datetime',
        ];
    }

    public function elevator(): BelongsTo
    {
        return $this->belongsTo(Elevator::class);
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}

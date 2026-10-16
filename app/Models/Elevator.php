<?php

namespace App\Models;

use App\Enums\ElevatorDirection;
use App\Enums\ElevatorStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Elevator extends Model
{
    protected $fillable = [
        'name',
        'current_floor',
        'direction',
        'status',
        'max_weight_kg',
        'max_passengers',
        'min_floor',
        'max_floor',
    ];

    protected function casts(): array
    {
        return [
            'current_floor' => 'integer',
            'direction' => ElevatorDirection::class,
            'status' => ElevatorStatus::class,
            'max_weight_kg' => 'decimal:2',
            'max_passengers' => 'integer',
            'min_floor' => 'integer',
            'max_floor' => 'integer',
        ];
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    public function calls(): HasMany
    {
        return $this->hasMany(ElevatorCall::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ElevatorEvent::class);
    }
}

<?php

namespace App\Models;

use App\Enums\TripStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Trip extends Model
{
    protected $fillable = [
        'elevator_id',
        'origin_floor',
        'destination_floor',
        'direction',
        'started_at',
        'finished_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'origin_floor' => 'integer',
            'destination_floor' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'status' => TripStatus::class,
        ];
    }

    public function elevator(): BelongsTo
    {
        return $this->belongsTo(Elevator::class);
    }

    public function passengers(): HasMany
    {
        return $this->hasMany(TripPassenger::class);
    }

    public function scopeMovements($query)
    {
        return $query->where('status', TripStatus::Completed);
    }
}

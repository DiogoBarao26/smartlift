<?php

namespace App\Models;

use App\Enums\CallStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ElevatorCall extends Model
{
    protected $fillable = [
        'elevator_id',
        'requested_floor',
        'requested_direction',
        'status',
        'requested_at',
        'attended_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_floor' => 'integer',
            'status' => CallStatus::class,
            'requested_at' => 'datetime',
            'attended_at' => 'datetime',
        ];
    }

    public function elevator(): BelongsTo
    {
        return $this->belongsTo(Elevator::class);
    }
}

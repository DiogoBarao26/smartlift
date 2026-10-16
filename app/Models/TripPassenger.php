<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripPassenger extends Model
{
    protected $fillable = [
        'trip_id',
        'person_id',
        'weight_at_entry',
        'destination_floor',
        'boarded_at',
        'exited_at',
    ];

    protected function casts(): array
    {
        return [
            'weight_at_entry' => 'decimal:2',
            'destination_floor' => 'integer',
            'boarded_at' => 'datetime',
            'exited_at' => 'datetime',
        ];
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

<?php

namespace App\Models;

use App\Enums\CardStatus;
use Database\Factories\PersonFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Person extends Model
{
    /** @use HasFactory<PersonFactory> */
    use HasFactory;

    protected $table = 'people';

    protected $fillable = [
        'name',
        'card_code',
        'destination_floor',
        'weight_kg',
        'card_status',
    ];

    protected function casts(): array
    {
        return [
            'destination_floor' => 'integer',
            'weight_kg' => 'decimal:2',
            'card_status' => CardStatus::class,
        ];
    }

    public function tripPassengers(): HasMany
    {
        return $this->hasMany(TripPassenger::class);
    }

    public function isInsideElevator(): bool
    {
        return $this->tripPassengers()->whereNull('exited_at')->exists();
    }
}

<?php

namespace Database\Factories;

use App\Enums\CardStatus;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Person>
 */
class PersonFactory extends Factory
{
    protected $model = Person::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'card_code' => strtoupper(fake()->unique()->bothify('CARD-####')),
            'destination_floor' => fake()->numberBetween(1, 5),
            'weight_kg' => fake()->randomFloat(2, 50, 110),
            'card_status' => CardStatus::Active,
        ];
    }

    public function blocked(): static
    {
        return $this->state(fn () => [
            'card_status' => CardStatus::Blocked,
        ]);
    }
}

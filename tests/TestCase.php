<?php

namespace Tests;

use App\Models\Elevator;
use App\Models\Person;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function createElevator(array $overrides = []): Elevator
    {
        return Elevator::query()->create(array_merge([
            'name' => 'Elevador Social',
            'current_floor' => 1,
            'direction' => 'idle',
            'status' => 'idle',
            'max_weight_kg' => 300,
            'max_passengers' => 4,
            'min_floor' => 1,
            'max_floor' => 5,
        ], $overrides));
    }

    protected function createPerson(array $overrides = []): Person
    {
        return Person::query()->create(array_merge([
            'name' => 'Ana Teste',
            'card_code' => 'TST-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'destination_floor' => 4,
            'weight_kg' => 70,
            'card_status' => 'active',
        ], $overrides));
    }
}

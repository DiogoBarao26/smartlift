<?php

namespace Database\Seeders;

use App\Enums\CardStatus;
use App\Enums\ElevatorDirection;
use App\Enums\ElevatorStatus;
use App\Models\Elevator;
use App\Models\Person;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SmartLiftSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@smartlift.local'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('admin123'),
                'email_verified_at' => now(),
            ],
        );

        Elevator::query()->updateOrCreate(
            ['name' => 'Elevador Social'],
            [
                'current_floor' => 1,
                'direction' => ElevatorDirection::Idle,
                'status' => ElevatorStatus::Idle,
                'max_weight_kg' => 300,
                'max_passengers' => 4,
                'min_floor' => 1,
                'max_floor' => 5,
            ],
        );

        $people = [
            ['Ana Beatriz Nogueira', 'ANA-1001', 5, 62, CardStatus::Active],
            ['Bruno Carvalho', 'BRU-1002', 3, 84, CardStatus::Active],
            ['Carla Mendes', 'CAR-1003', 4, 70, CardStatus::Active],
            ['Diego Albuquerque', 'DIE-1004', 2, 96, CardStatus::Active],
            ['Elisa Pacheco', 'ELI-1005', 5, 58, CardStatus::Active],
            ['Fábio Monteiro', 'FAB-1006', 4, 190, CardStatus::Active],
            ['Helena Duarte', 'HEL-1007', 3, 73, CardStatus::Blocked],
        ];

        foreach ($people as [$name, $code, $floor, $weight, $status]) {
            Person::query()->updateOrCreate(
                ['card_code' => $code],
                [
                    'name' => $name,
                    'destination_floor' => $floor,
                    'weight_kg' => $weight,
                    'card_status' => $status,
                ],
            );
        }
    }
}

<?php

namespace App\Enums;

enum ElevatorDirection: string
{
    case Up = 'up';
    case Down = 'down';
    case Idle = 'idle';

    public function label(): string
    {
        return match ($this) {
            self::Up => 'Subindo',
            self::Down => 'Descendo',
            self::Idle => 'Parado',
        };
    }
}

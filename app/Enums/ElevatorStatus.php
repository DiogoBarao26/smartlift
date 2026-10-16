<?php

namespace App\Enums;

enum ElevatorStatus: string
{
    case Idle = 'idle';
    case MovingUp = 'moving_up';
    case MovingDown = 'moving_down';
    case DoorsOpening = 'doors_opening';
    case DoorsOpen = 'doors_open';
    case DoorsClosing = 'doors_closing';

    public function label(): string
    {
        return match ($this) {
            self::Idle => 'Parado',
            self::MovingUp => 'Subindo',
            self::MovingDown => 'Descendo',
            self::DoorsOpening => 'Abrindo portas',
            self::DoorsOpen => 'Portas abertas',
            self::DoorsClosing => 'Fechando portas',
        };
    }
}

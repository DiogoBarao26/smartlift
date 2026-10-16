<?php

namespace App\Enums;

enum CardStatus: string
{
    case Active = 'active';
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Ativo',
            self::Blocked => 'Bloqueado',
        };
    }
}

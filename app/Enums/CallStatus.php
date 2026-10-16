<?php

namespace App\Enums;

enum CallStatus: string
{
    case Pending = 'pending';
    case Attended = 'attended';
    case Cancelled = 'cancelled';
}

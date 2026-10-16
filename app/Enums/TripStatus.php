<?php

namespace App\Enums;

enum TripStatus: string
{
    case Boarding = 'boarding';
    case Completed = 'completed';
    case Fulfilled = 'fulfilled';
}

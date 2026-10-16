<?php

namespace App\Enums;

enum ElevatorEventType: string
{
    case CallRequested = 'call_requested';
    case CallAttended = 'call_attended';
    case DoorsClosed = 'doors_closed';
    case MoveStarted = 'move_started';
    case Arrived = 'arrived';
    case DoorsOpened = 'doors_opened';
    case BoardAuthorized = 'board_authorized';
    case BoardRefused = 'board_refused';
    case Alighted = 'alighted';
    case ConfigUpdated = 'config_updated';
}

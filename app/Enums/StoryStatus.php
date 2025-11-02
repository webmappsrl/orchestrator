<?php

namespace App\Enums;

enum StoryStatus: string
{
    case Backlog = 'backlog';
    case New = 'new';
    case Assigned = 'assigned';
    case Todo = 'todo';
    case Progress = 'progress';
    case Test = 'testing';
    case Tested = 'tested';
    case Waiting = 'waiting';
    case Problem = 'problem';
    case Done = 'done';
    case Rejected = 'rejected';
    case Released = 'released';

    // Metodo per ottenere un array di valori
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

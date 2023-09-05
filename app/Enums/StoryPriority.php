<?php

namespace App\Enums;

enum StoryPriority: int
{
    case Low = 1;
    case Medium = 2;
    case High = 3;

    public static function getCase(int $value): string
    {
        return match ($value) {
            1 => 'Low',
            2 => 'Medium',
            3 => 'High',
        };
    }
}

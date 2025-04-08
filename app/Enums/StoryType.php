<?php

namespace App\Enums;

enum StoryType: string
{
    case Bug = 'Bug';
    case Feature = 'Feature';
    case Helpdesk = 'Help desk';
    case Scrum = 'Scrum';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

<?php

namespace App\Enums;

enum StoryType: string
{
    case Bug = 'Bug';
    case Feature = 'Feature';
    case Helpdesk = 'Help desk';
}

<?php

namespace App\Enums;

enum StoryStatus: string
{
    case New = 'new';
    case Progress = 'progress';
    case Test = 'test';
    case Rejected = 'rejected';
    case Done = 'done';
}

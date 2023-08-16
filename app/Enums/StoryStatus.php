<?php

namespace App\Enums;

enum StoryStatus: string
{
    case New = 'new';
    case Progress = 'progress';
    case Test = 'testing';
    case Rejected = 'rejected';
    case Done = 'done';
}

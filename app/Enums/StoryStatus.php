<?php

namespace App\Enums;

enum StoryStatus: string
{
    case New = 'new';
    case Progress = 'in progress';
    case Test = 'testing';
    case Rejected = 'rejected';
    case Done = 'done';
}

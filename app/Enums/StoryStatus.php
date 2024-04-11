<?php

namespace App\Enums;

enum StoryStatus: string
{
    case New = 'new';
    case Assigned = 'assigned';
    case Progress = 'progress';
    case Test = 'testing';
    case Tested = 'tested';
    case Done = 'done';
    case Rejected = 'rejected';
    case Released = 'released';
}

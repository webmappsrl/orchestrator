<?php

namespace App\Enums;

enum EpicStatus: string
{
    case New = 'new';
    case Progress = 'progress';
    case Test = 'test';
    case Done = 'done';
    case Rejected = 'rejected';
}
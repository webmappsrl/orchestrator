<?php

namespace App\Enums;

enum UserRole: string
{
    case New = 'new';
    case Progress = 'progress';
    case Test = 'test';
    case Rejected = 'rejected';
    case Done = 'done';
}

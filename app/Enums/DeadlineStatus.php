<?php

namespace App\Enums;

enum DeadlineStatus: string
{
    case New = 'new';
    case Progress = 'progress';
    case Done = 'done';
    case Expired = 'expired';
}

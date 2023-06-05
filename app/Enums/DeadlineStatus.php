<?php

namespace App\Enums;

enum DeadlineStatus: string
{
    case New = 'new';
    case Progress = 'in progress';
    case Done = 'done';
    case Expired = 'expired';
}

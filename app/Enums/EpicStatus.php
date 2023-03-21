<?php

namespace App\Enums;

enum EpicStatus: string
{
    case New = 'new';
    case Progress = 'in progress';
    case Test = 'testing';
    case Done = 'done';
    case Rejected = 'rejected';
    case Project = 'project';
}

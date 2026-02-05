<?php

namespace App\Enums;

enum CustomerStatus: string
{
    case Unknown = 'unknown';
    case Opportunity = 'opportunity';
    case Active = 'active';
    case Lost = 'lost';
}

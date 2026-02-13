<?php

namespace App\Enums;

enum CustomerStatus: string
{
    case Unknown = 'unknown';
    case Opportunity = 'opportunity';
    case Active = 'active';
    case Lost = 'lost';

    /**
     * Nova Badge style for index/detail display.
     */
    public function badgeStyle(): string
    {
        return match ($this) {
            self::Unknown => 'info',
            self::Opportunity => 'warning',
            self::Active => 'success',
            self::Lost => 'danger',
        };
    }
}

<?php

namespace App\Enums;

enum OwnerType: string
{
    case Customer = 'customer';
    case Organization = 'organization';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}


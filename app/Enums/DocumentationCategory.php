<?php

namespace App\Enums;

enum DocumentationCategory: string
{
    case Internal = 'internal';
    case Customer = 'customer';

    public static function labels(): array
    {
        return [
            self::Internal->value => 'Internal',
            self::Customer->value => 'Customer',
        ];
    }
}

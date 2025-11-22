<?php

namespace App\Enums;

enum ReportType: string
{
    case Annual = 'annual';
    case Monthly = 'monthly';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}


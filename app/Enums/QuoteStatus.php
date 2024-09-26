<?php

namespace App\Enums;

enum QuoteStatus: string
{
    case New = 'new';
    case Sent = 'sent';
    case Closed_Lost = 'closed lost';
    case Closed_Won = 'closed won';
    case Partially_Paid = 'partially paid';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::New => __('New'),
            self::Sent => __('Sent'),
            self::Closed_Lost => __('Closed Lost'),
            self::Closed_Won => __('Closed Won'),
            self::Partially_Paid => __('Partially Paid'),
            self::Paid => __('Paid'),
        };
    }
}

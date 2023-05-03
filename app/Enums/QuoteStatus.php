<?php

namespace App\Enums;

enum QuoteStatus: string
{
    case New = 'new';
    case Sent = 'sent';
    case Closed_Lost = 'closed lost';
    case Closed_Won = 'closed won';
    case Paid = 'paid';
    case Partially_Paid = 'partially paid';
}

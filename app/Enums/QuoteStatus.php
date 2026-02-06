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
    case To_Present = 'to present';
    case Presented = 'presented';
    case Waiting_For_Order = 'waiting for order';
    case Cold = 'cold';
    case Closed_Won_Offer = 'closed won offer';
    case Closed_Lost_Offer = 'closed lost offer';
}

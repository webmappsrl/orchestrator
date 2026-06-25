<?php

namespace Webmapp\TeamPerformance;

use Laravel\Nova\Card;

class TeamPerformanceCard extends Card
{
    public $width = 'full';

    public function component(): string
    {
        return 'team-performance-card';
    }
}

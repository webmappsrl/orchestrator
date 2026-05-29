<?php

namespace Webmapp\HetznerMonitoring;

use Laravel\Nova\Card;

class HetznerMonitoringCard extends Card
{
    public $width = 'full';

    public function component(): string
    {
        return 'hetzner-monitoring-card';
    }
}

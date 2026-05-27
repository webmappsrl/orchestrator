<?php

namespace App\Nova\Dashboards;

use Laravel\Nova\Dashboard;
use Webmapp\HetznerMonitoring\HetznerMonitoringCard;

class HetznerMonitoring extends Dashboard
{
    public function cards(): array
    {
        return [
            (new HetznerMonitoringCard)->width('full'),
        ];
    }

    public function label(): string
    {
        return 'Hetzner Monitoring';
    }
}

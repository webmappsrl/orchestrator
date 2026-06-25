<?php

namespace App\Nova\Dashboards;

use Laravel\Nova\Dashboard;
use Webmapp\TeamPerformance\TeamPerformanceCard;

class TeamPerformance extends Dashboard
{
    public function label(): string
    {
        return 'Team Performance';
    }

    public function cards(): array
    {
        return [
            new TeamPerformanceCard(),
        ];
    }

    public function uriKey(): string
    {
        return 'team-performance';
    }
}

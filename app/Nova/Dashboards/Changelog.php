<?php

namespace App\Nova\Dashboards;

use App\Services\ChangelogService;
use Illuminate\Http\Request;
use InteractionDesignFoundation\HtmlCard\HtmlCard;
use Laravel\Nova\Dashboard;

class Changelog extends Dashboard
{
    /**
     * Get the cards for the dashboard.
     *
     * @return array
     */
    public function cards()
    {
        $changelogService = app(ChangelogService::class);
        $minorReleases = $changelogService->getMinorReleases();
        
        // Show the index page with menu and links to minor release dashboards
        return [
            (new HtmlCard)
                ->width('full')
                ->view('changelog-dashboard-index', [
                    'minorReleases' => $minorReleases,
                ])
                ->center(true),
        ];
    }

    /**
     * Get the displayable name of the dashboard.
     *
     * @return string
     */
    public function name()
    {
        return __('Changelog');
    }

    /**
     * Get the URI key for the dashboard.
     *
     * @return string
     */
    public function uriKey()
    {
        return 'changelog';
    }

}

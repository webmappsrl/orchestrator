<?php

namespace App\Nova\Dashboards;

use App\Enums\UserRole;
use Laravel\Nova\Cards\Help;
use App\Nova\Metrics\TotApps;
use App\Nova\Metrics\TotEpics;
use App\Nova\Metrics\TotLayers;
use App\Nova\Metrics\TotStories;
use App\Nova\Metrics\TotProjects;
use App\Nova\Metrics\TotCustomers;
use App\Nova\Metrics\TotMilestones;
use Laravel\Nova\Dashboards\Main as Dashboard;
use InteractionDesignFoundation\HtmlCard\HtmlCard;
use App\Services\StoryFetcher;
use Illuminate\Support\Facades\Log;

class Main extends Dashboard
{
    /*---Helper---*/
    protected function storyCard(string $status, string $label, $user, $width = '1/3')
    {
        $stories = StoryFetcher::byStatusAndUser($status, $user);
        Log::debug('Stories full data: ' . json_encode($stories, JSON_PRETTY_PRINT));

        return (new HtmlCard)
            ->width($width)
            ->view('story-viewer', [
                'stories' => $stories,
                'statusLabel' => $label,
            ])
            ->center(true);
    }


    /**
     * Get the cards for the dashboard.
     *
     * @return array
     */
    public function cards()
    {
        $user = auth()->user();

        return [
            $this->storyCard('progress', __('Progress'), $user, 'full'),
            $this->storyCard('todo', __('To Do'), $user, 'full'),
            $this->storyCard('testing', __('Test'), $user, '1/2'),
            $this->storyCard('tested', __('Tested'), $user, '1/2'),

            (new TotCustomers)->canSee(function ($request) {
                $user = $request->user();
                return $user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Manager);
            }),

            (new TotProjects)->canSee(function ($request) {
                $user = $request->user();
                return $user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Manager);
            }),

            (new TotMilestones)->canSee(function ($request) {
                $user = $request->user();
                return $user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Admin);
            }),

            (new TotEpics)->canSee(function ($request) {
                $user = $request->user();
                return $user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Admin);
            }),

            (new TotStories)->canSee(function ($request) {
                $user = $request->user();
                return $user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Developer);
            }),

            (new TotApps)->canSee(function ($request) {
                $user = $request->user();
                return $user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Editor);
            }),

            (new TotLayers)->canSee(function ($request) {
                $user = $request->user();
                return $user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Editor);
            }),

            (new HtmlCard)->width('1/3')->view('favorite')->canSee(function ($request) {
                $user = $request->user();
                return $user->hasRole(UserRole::Admin);
            })->center(true),
        ];
    }
}

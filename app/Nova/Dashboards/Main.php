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
    protected function storyCard(string $status, string $label, $user)
    {
        $stories = StoryFetcher::byStatusAndUser($status, $user);
        Log::debug('Stories full data: ' . json_encode($stories, JSON_PRETTY_PRINT));

        return (new HtmlCard)
            ->width('1/3')
            ->view('story-viewer')
            ->withMeta([
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
                return $user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Developer);
            }),

            (new TotEpics)->canSee(function ($request) {
                $user = $request->user();
                return $user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Developer);
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
                return $user->hasRole(UserRole::Developer);
            })->center(true),

            $this->storyCard('todo', __('To Do'), $user),
            $this->storyCard('progress', __('Progress'), $user),
            $this->storyCard('tobetested', __('Test'), $user),
            $this->storyCard('tested', __('Tested'), $user),

        ];
    }
}

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
use NormanHuth\NovaResourceCard\NovaResourceCard;

class Main extends Dashboard
{
    /**
     * Get the cards for the dashboard.
     *
     * @return array
     */
    public function cards()
    {
        $loggedInUser = auth()->user();
        $userId = $loggedInUser->id;
        $url = url()->to('/');
        $url .= '/resources/users/' . $userId . '/attach/projects?viaRelationship=projects&polymorphic=0';
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

            (new NovaResourceCard('\App\Nova\FavoriteProjects'))
                ->width('full')
                ->setFooterLinkLabel(__('Attach projects to your favorites'))
                ->setFooterLinkExternalUrl($url)
        ];
    }
}

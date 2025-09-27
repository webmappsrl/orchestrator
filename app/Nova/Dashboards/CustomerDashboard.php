<?php

namespace App\Nova\Dashboards;

use App\Enums\UserRole;
use App\Models\FundraisingOpportunity;
use App\Models\FundraisingProject;
use App\Models\User;
use InteractionDesignFoundation\HtmlCard\HtmlCard;
use Laravel\Nova\Dashboard;

class CustomerDashboard extends Dashboard
{
    /**
     * Get the cards for the dashboard.
     *
     * @return array
     */
    public function cards()
    {
        return [
            $this->fundraisingOpportunitiesCard(),
            $this->myProjectsCard(),
            $this->recentActivityCard(),
        ];
    }

    /**
     * Card showing active fundraising opportunities
     */
    protected function fundraisingOpportunitiesCard()
    {
        $opportunities = FundraisingOpportunity::where('deadline', '>=', now())
            ->orderBy('deadline', 'asc')
            ->limit(5)
            ->get();

        return (new HtmlCard)
            ->width('1/3')
            ->view('customer-dashboard.opportunities', [
                'opportunities' => $opportunities,
            ])
            ->canSee(function ($request) {
                return $request->user()->hasRole(UserRole::Customer);
            });
    }

    /**
     * Card showing user's fundraising projects
     */
    protected function myProjectsCard()
    {
        $user = auth()->user();
        $projects = FundraisingProject::where(function ($query) use ($user) {
            $query->where('lead_user_id', $user->id)
                  ->orWhereHas('partners', function ($q) use ($user) {
                      $q->where('user_id', $user->id);
                  });
        })
        ->with(['fundraisingOpportunity', 'leadUser'])
        ->orderBy('updated_at', 'desc')
        ->limit(5)
        ->get();

        return (new HtmlCard)
            ->width('1/3')
            ->view('customer-dashboard.projects', [
                'projects' => $projects,
            ])
            ->canSee(function ($request) {
                return $request->user()->hasRole(UserRole::Customer);
            });
    }

    /**
     * Card showing recent activity
     */
    protected function recentActivityCard()
    {
        $user = auth()->user();
        
        // Get recent stories related to fundraising projects
        $recentStories = \App\Models\Story::whereHas('fundraisingProject', function ($query) use ($user) {
            $query->where('lead_user_id', $user->id)
                  ->orWhereHas('partners', function ($q) use ($user) {
                      $q->where('user_id', $user->id);
                  });
        })
        ->with(['fundraisingProject'])
        ->orderBy('updated_at', 'desc')
        ->limit(5)
        ->get();

        return (new HtmlCard)
            ->width('1/3')
            ->view('customer-dashboard.activity', [
                'stories' => $recentStories,
            ])
            ->canSee(function ($request) {
                return $request->user()->hasRole(UserRole::Customer);
            });
    }

    /**
     * Get the URI key for the dashboard.
     *
     * @return string
     */
    public function uriKey()
    {
        return 'customer-dashboard';
    }
}

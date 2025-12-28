<?php

namespace App\Nova\Dashboards;

use App\Enums\StoryStatus;
use App\Enums\UserRole;
use App\Models\FundraisingProject;
use App\Models\Story;
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
            $this->loginInfoCard(),
            $this->documentationCard(),
            $this->ticketsToCompleteCard(),
            $this->fspProjectsCard(),
            $this->storageCard(),
            $this->budgetCard(),
        ];
    }

    /**
     * Card showing login information
     */
    protected function loginInfoCard()
    {
        $user = auth()->user();
        
        $loginInfo = [
            'name' => $user->name,
            'email' => $user->email,
            'last_login' => $user->last_login_at ?? 'Mai',
        ];

        return (new HtmlCard)
            ->width('1/3')
            ->view('customer-dashboard.login-info', [
                'loginInfo' => $loginInfo,
            ])
            ->canSee(function ($request) {
                return $request->user()->hasRole(UserRole::Customer);
            });
    }

    /**
     * Card showing documentation link
     */
    protected function documentationCard()
    {
        return (new HtmlCard)
            ->width('1/3')
            ->view('customer-dashboard.documentation')
            ->canSee(function ($request) {
                return $request->user()->hasRole(UserRole::Customer);
            });
    }

    /**
     * Card showing number of tickets to complete
     */
    protected function ticketsToCompleteCard()
    {
        $user = auth()->user();
        
        $ticketStatuses = [
            StoryStatus::New->value,
            StoryStatus::Backlog->value,
            StoryStatus::Assigned->value,
            StoryStatus::Todo->value,
            StoryStatus::Progress->value,
            StoryStatus::Test->value,
            StoryStatus::Problem->value,
            StoryStatus::Waiting->value,
        ];

        $ticketsCount = Story::where('creator_id', $user->id)
            ->whereIn('status', $ticketStatuses)
            ->count();

        return (new HtmlCard)
            ->width('1/3')
            ->view('customer-dashboard.tickets-to-complete', [
                'ticketsCount' => $ticketsCount,
            ])
            ->canSee(function ($request) {
                return $request->user()->hasRole(UserRole::Customer);
            });
    }

    /**
     * Card showing number of FSP projects
     */
    protected function fspProjectsCard()
    {
        $user = auth()->user();
        
        $fspProjectsCount = FundraisingProject::where(function ($query) use ($user) {
            $query->where('lead_user_id', $user->id)
                  ->orWhereHas('partners', function ($q) use ($user) {
                      $q->where('user_id', $user->id);
                  });
        })->count();

        return (new HtmlCard)
            ->width('1/3')
            ->view('customer-dashboard.fsp-projects', [
                'fspProjectsCount' => $fspProjectsCount,
            ])
            ->canSee(function ($request) {
                return $request->user()->hasRole(UserRole::Customer);
            });
    }

    /**
     * Card showing Google Drive storage access
     */
    protected function storageCard()
    {
        $user = auth()->user();
        
        return (new HtmlCard)
            ->width('1/3')
            ->view('customer-dashboard.storage', [
                'googleDriveUrl' => $user->google_drive_url,
            ])
            ->canSee(function ($request) {
                return $request->user()->hasRole(UserRole::Customer);
            });
    }

    /**
     * Card showing Google Drive budget access
     */
    protected function budgetCard()
    {
        $user = auth()->user();
        
        return (new HtmlCard)
            ->width('1/3')
            ->view('customer-dashboard.budget', [
                'googleDriveBudgetUrl' => $user->google_drive_budget_url,
            ])
            ->canSee(function ($request) {
                return $request->user()->hasRole(UserRole::Customer);
            });
    }

    /**
     * Get the displayable name of the dashboard.
     *
     * @return string
     */
    public function name()
    {
        return __('Dashboard');
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

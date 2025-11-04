<?php

namespace App\Nova\Dashboards;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\UsersStoriesLog;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use InteractionDesignFoundation\HtmlCard\HtmlCard;
use Laravel\Nova\Dashboard;

class Activity extends Dashboard
{
    /**
     * Get the selected user (from session or current user)
     */
    protected function getSelectedUser()
    {
        /** @var User $currentUser */
        $currentUser = auth()->user();
        $selectedUserId = session('activity_selected_user_id');

        if ($selectedUserId) {
            $selectedUser = User::find($selectedUserId);

            if ($selectedUser) {
                return $selectedUser;
            }
        }

        return $currentUser;
    }

    /**
     * Get the selected date range (from session or default last 30 days)
     */
    protected function getSelectedDateRange()
    {
        $startDate = session('activity_start_date', Carbon::now()->subDays(30)->format('Y-m-d'));
        $endDate = session('activity_end_date', Carbon::now()->format('Y-m-d'));

        return [
            'start' => Carbon::parse($startDate),
            'end' => Carbon::parse($endDate),
        ];
    }

    /**
     * Create a selector card for user and date range
     */
    protected function selectorCard()
    {
        $developers = User::whereJsonContains('roles', UserRole::Developer->value)
            ->orderBy('name')
            ->get();

        $selectedUser = $this->getSelectedUser();
        $dateRange = $this->getSelectedDateRange();

        return (new HtmlCard)
            ->width('full')
            ->view('activity-selector', [
                'developers' => $developers,
                'currentUser' => auth()->user(),
                'selectedUser' => $selectedUser,
                'startDate' => $dateRange['start']->format('Y-m-d'),
                'endDate' => $dateRange['end']->format('Y-m-d'),
            ])
            ->canSee(function ($request) {
                /** @var User $user */
                $user = $request->user();
                if ($user == null) {
                    return false;
                }

                return $user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Developer);
            })
            ->center(true);
    }

    /**
     * Get activities for selected user and date range
     */
    protected function getActivities(Authenticatable $user, Carbon $startDate, Carbon $endDate)
    {
        return UsersStoriesLog::where('user_id', $user->id)
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->where('elapsed_minutes', '>', 0)
            ->with(['story.tags', 'story.creator', 'user'])
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Create a card for activity table
     */
    protected function activityTableCard(Authenticatable $user, Carbon $startDate, Carbon $endDate)
    {
        $activities = $this->getActivities($user, $startDate, $endDate);

        // Group by date and calculate daily totals
        $groupedByDate = $activities->groupBy(function ($activity) {
            return $activity->date->format('Y-m-d');
        });

        $totalMinutes = $activities->sum('elapsed_minutes');
        $totalHours = floor($totalMinutes / 60);
        $totalMinutesRemainder = $totalMinutes % 60;

        return (new HtmlCard)
            ->width('full')
            ->view('activity-table', [
                'activities' => $activities,
                'groupedByDate' => $groupedByDate,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'totalHours' => $totalHours,
                'totalMinutes' => $totalMinutesRemainder,
                'selectedUser' => $user,
            ])
            ->canSee(function ($request) {
                /** @var User $user */
                $user = $request->user();
                if ($user == null) {
                    return false;
                }

                return $user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Developer);
            })
            ->center(true);
    }

    /**
     * Get the cards for the dashboard.
     *
     * @return array
     */
    public function cards()
    {
        $user = $this->getSelectedUser();
        $dateRange = $this->getSelectedDateRange();

        return [
            $this->selectorCard(),
            $this->activityTableCard($user, $dateRange['start'], $dateRange['end']),
        ];
    }

    /**
     * Get the displayable name of the dashboard.
     *
     * @return string
     */
    public function name()
    {
        return __('Activity');
    }

    /**
     * Get the URI key for the dashboard.
     *
     * @return string
     */
    public function uriKey()
    {
        return 'activity';
    }
}


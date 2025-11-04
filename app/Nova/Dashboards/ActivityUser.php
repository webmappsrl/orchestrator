<?php

namespace App\Nova\Dashboards;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\UsersStoriesLog;
use Illuminate\Support\Carbon;
use InteractionDesignFoundation\HtmlCard\HtmlCard;
use Laravel\Nova\Dashboard;

class ActivityUser extends Dashboard
{
    /**
     * Get the selected date range (from session or default last 30 days)
     */
    protected function getSelectedDateRange()
    {
        $startDate = session('activity_user_start_date', Carbon::now()->subDays(30)->format('Y-m-d'));
        $endDate = session('activity_user_end_date', Carbon::now()->format('Y-m-d'));

        return [
            'start' => Carbon::parse($startDate),
            'end' => Carbon::parse($endDate),
        ];
    }

    /**
     * Create a selector card for date range
     */
    protected function selectorCard()
    {
        $dateRange = $this->getSelectedDateRange();

        return (new HtmlCard)
            ->width('full')
            ->view('activity-user-selector', [
                'startDate' => $dateRange['start']->format('Y-m-d'),
                'endDate' => $dateRange['end']->format('Y-m-d'),
            ])
            ->canSee(function ($request) {
                /** @var User $user */
                $user = $request->user();
                if ($user == null) {
                    return false;
                }

                return $user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Manager);
            })
            ->center(true);
    }

    /**
     * Get all activities aggregated by date and user for all users
     */
    protected function getActivitiesByDateAndUser(Carbon $startDate, Carbon $endDate)
    {
        $activities = UsersStoriesLog::whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->where('elapsed_minutes', '>', 0)
            ->with(['user', 'story'])
            ->get();

        // Group by date, then by user
        $groupedByDateAndUser = $activities->groupBy(function ($activity) {
            return $activity->date->format('Y-m-d');
        })->map(function ($dayActivities) {
            return $dayActivities->groupBy('user_id');
        })->sortKeysDesc(); // Sort by date descending

        return $groupedByDateAndUser;
    }

    /**
     * Create a card for activity table
     */
    protected function activityTableCard(Carbon $startDate, Carbon $endDate)
    {
        $groupedByDateAndUser = $this->getActivitiesByDateAndUser($startDate, $endDate);

        // Calculate total for the period
        $totalMinutes = 0;
        $totalTickets = 0;
        $allElapsedMinutes = [];
        foreach ($groupedByDateAndUser as $dayActivities) {
            foreach ($dayActivities as $userActivities) {
                $totalMinutes += $userActivities->sum('elapsed_minutes');
                $totalTickets += $userActivities->count();
                // Collect all elapsed minutes for min/max calculation
                foreach ($userActivities as $activity) {
                    $allElapsedMinutes[] = $activity->elapsed_minutes;
                }
            }
        }
        $totalHours = floor($totalMinutes / 60);
        $totalMinutesRemainder = $totalMinutes % 60;
        
        // Calculate average time per ticket
        $avgMinutes = $totalTickets > 0 ? round($totalMinutes / $totalTickets) : 0;
        $avgHours = floor($avgMinutes / 60);
        $avgMinutesRemainder = $avgMinutes % 60;
        
        // Calculate min and max durations
        $minMinutes = !empty($allElapsedMinutes) ? min($allElapsedMinutes) : 0;
        $maxMinutes = !empty($allElapsedMinutes) ? max($allElapsedMinutes) : 0;
        $minHours = floor($minMinutes / 60);
        $minMinutesRemainder = $minMinutes % 60;
        $maxHours = floor($maxMinutes / 60);
        $maxMinutesRemainder = $maxMinutes % 60;

        return (new HtmlCard)
            ->width('full')
            ->view('activity-user-table', [
                'groupedByDateAndUser' => $groupedByDateAndUser,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'totalHours' => $totalHours,
                'totalMinutes' => $totalMinutesRemainder,
                'totalTickets' => $totalTickets,
                'avgHours' => $avgHours,
                'avgMinutes' => $avgMinutesRemainder,
                'minHours' => $minHours,
                'minMinutes' => $minMinutesRemainder,
                'maxHours' => $maxHours,
                'maxMinutes' => $maxMinutesRemainder,
            ])
            ->canSee(function ($request) {
                /** @var User $user */
                $user = $request->user();
                if ($user == null) {
                    return false;
                }

                return $user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Manager);
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
        $dateRange = $this->getSelectedDateRange();

        return [
            $this->selectorCard(),
            $this->activityTableCard($dateRange['start'], $dateRange['end']),
        ];
    }

    /**
     * Get the displayable name of the dashboard.
     *
     * @return string
     */
    public function name()
    {
        return __('Timetable');
    }

    /**
     * Get the URI key for the dashboard.
     *
     * @return string
     */
    public function uriKey()
    {
        return 'activity-user';
    }
}


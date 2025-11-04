<?php

namespace App\Nova\Dashboards;

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;
use App\Models\UsersStoriesLog;
use Illuminate\Support\Carbon;
use InteractionDesignFoundation\HtmlCard\HtmlCard;
use Laravel\Nova\Dashboard;

class ActivityOrganizations extends Dashboard
{
    /**
     * Get the selected date range (from session or default last 30 days)
     */
    protected function getSelectedDateRange()
    {
        $startDate = session('activity_organizations_start_date', Carbon::now()->subDays(30)->format('Y-m-d'));
        $endDate = session('activity_organizations_end_date', Carbon::now()->format('Y-m-d'));

        return [
            'start' => Carbon::parse($startDate),
            'end' => Carbon::parse($endDate),
        ];
    }

    /**
     * Get the selected organization filter (from session or null)
     */
    protected function getSelectedOrganizationFilter()
    {
        return session('activity_organizations_organization_filter', null);
    }

    /**
     * Create a selector card for date range and organization filter
     */
    protected function selectorCard()
    {
        $dateRange = $this->getSelectedDateRange();
        $selectedOrganizationFilter = $this->getSelectedOrganizationFilter();

        return (new HtmlCard)
            ->width('full')
            ->view('activity-organizations-selector', [
                'startDate' => $dateRange['start']->format('Y-m-d'),
                'endDate' => $dateRange['end']->format('Y-m-d'),
                'selectedOrganizationFilter' => $selectedOrganizationFilter,
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
     * Get all activities aggregated by organization (through story -> creator -> organizations)
     */
    protected function getActivitiesByOrganization(Carbon $startDate, Carbon $endDate, $organizationFilter = null)
    {
        $activities = UsersStoriesLog::whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->where('elapsed_minutes', '>', 0)
            ->with(['story.creator.organizations'])
            ->get();

        // Group by organization - each activity can have multiple organizations (if creator has multiple), so we need to expand
        $groupedByOrganization = collect();
        
        foreach ($activities as $activity) {
            $story = $activity->story;
            if ($story && $story->creator) {
                $creator = $story->creator;
                $organizations = $creator->organizations;
                
                if ($organizations->isEmpty()) {
                    // If no organizations, group under "No Organization"
                    $organizationId = 0;
                    $organizationName = 'No Organization';
                    if (!$groupedByOrganization->has($organizationId)) {
                        $groupedByOrganization[$organizationId] = collect();
                    }
                    $groupedByOrganization[$organizationId]->push([
                        'activity' => $activity,
                        'organization_name' => $organizationName,
                    ]);
                } else {
                    // Add activity to each organization of the creator
                    foreach ($organizations as $organization) {
                        $organizationId = $organization->id;
                        $organizationName = $organization->name;
                        
                        // Apply organization filter if specified (LIKE search)
                        if ($organizationFilter && strpos(strtolower($organizationName), strtolower($organizationFilter)) === false) {
                            continue;
                        }
                        
                        if (!$groupedByOrganization->has($organizationId)) {
                            $groupedByOrganization[$organizationId] = collect();
                        }
                        $groupedByOrganization[$organizationId]->push([
                            'activity' => $activity,
                            'organization_name' => $organizationName,
                        ]);
                    }
                }
            } else {
                // If no story or story has no creator, group under "No Organization"
                $organizationId = 0;
                $organizationName = 'No Organization';
                
                // Apply organization filter if specified
                if ($organizationFilter && strpos(strtolower($organizationName), strtolower($organizationFilter)) === false) {
                    continue;
                }
                
                if (!$groupedByOrganization->has($organizationId)) {
                    $groupedByOrganization[$organizationId] = collect();
                }
                $groupedByOrganization[$organizationId]->push([
                    'activity' => $activity,
                    'organization_name' => $organizationName,
                ]);
            }
        }

        return $groupedByOrganization;
    }

    /**
     * Create a card for activity table
     */
    protected function activityTableCard(Carbon $startDate, Carbon $endDate)
    {
        $organizationFilter = $this->getSelectedOrganizationFilter();
        
        $activities = UsersStoriesLog::whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->where('elapsed_minutes', '>', 0)
            ->with(['story.creator.organizations'])
            ->get();
            
        $groupedByOrganization = $this->getActivitiesByOrganization($startDate, $endDate, $organizationFilter);

        // Calculate total for the period (based on filtered results)
        $organizationStats = [];
        $allUniqueStories = collect();
        $allFilteredElapsedMinutes = [];
        
        foreach ($groupedByOrganization as $organizationId => $organizationActivities) {
            // Get unique story IDs for this organization
            $uniqueStories = $organizationActivities->pluck('activity.story_id')->unique();
            
            // Sum all elapsed minutes for activities with this organization
            $organizationTotalMinutes = $organizationActivities->sum(function ($item) {
                return $item['activity']->elapsed_minutes;
            });
            
            // Get all elapsed minutes for min/max calculation
            $organizationElapsedMinutes = $organizationActivities->pluck('activity.elapsed_minutes')->toArray();
            
            $organizationStats[$organizationId] = [
                'name' => $organizationActivities->first()['organization_name'],
                'total_minutes' => $organizationTotalMinutes,
                'ticket_count' => $uniqueStories->count(),
                'elapsed_minutes' => $organizationElapsedMinutes,
            ];
            
            // Collect unique stories and elapsed minutes for total calculation
            $allUniqueStories = $allUniqueStories->merge($uniqueStories);
            $allFilteredElapsedMinutes = array_merge($allFilteredElapsedMinutes, $organizationElapsedMinutes);
        }
        
        // Total minutes: sum all filtered activities
        $totalMinutes = array_sum($allFilteredElapsedMinutes);
        // Total tickets: unique stories across filtered organizations
        $totalTickets = $allUniqueStories->unique()->count();
        
        $totalHours = floor($totalMinutes / 60);
        $totalMinutesRemainder = $totalMinutes % 60;
        
        // Calculate average time per ticket (using unique stories, not activities)
        $avgMinutes = $totalTickets > 0 ? round($totalMinutes / $totalTickets) : 0;
        $avgHours = floor($avgMinutes / 60);
        $avgMinutesRemainder = $avgMinutes % 60;
        
        // Calculate min and max durations (from filtered activities)
        $minMinutes = !empty($allFilteredElapsedMinutes) ? min($allFilteredElapsedMinutes) : 0;
        $maxMinutes = !empty($allFilteredElapsedMinutes) ? max($allFilteredElapsedMinutes) : 0;
        $minHours = floor($minMinutes / 60);
        $minMinutesRemainder = $minMinutes % 60;
        $maxHours = floor($maxMinutes / 60);
        $maxMinutesRemainder = $maxMinutes % 60;

        // Convert organizationStats to array and sort alphabetically
        $organizationStatsArray = array_values($organizationStats);
        
        return (new HtmlCard)
            ->width('full')
            ->view('activity-organizations-table', [
                'organizationStats' => $organizationStatsArray,
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
        return __('Organizations');
    }

    /**
     * Get the URI key for the dashboard.
     *
     * @return string
     */
    public function uriKey()
    {
        return 'activity-organizations';
    }
}


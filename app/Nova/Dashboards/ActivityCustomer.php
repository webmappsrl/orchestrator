<?php

namespace App\Nova\Dashboards;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\UsersStoriesLog;
use Illuminate\Support\Carbon;
use InteractionDesignFoundation\HtmlCard\HtmlCard;
use Laravel\Nova\Dashboard;

class ActivityCustomer extends Dashboard
{
    /**
     * Get the selected date range (from session or default last 30 days)
     */
    protected function getSelectedDateRange()
    {
        $startDate = session('activity_customer_start_date', Carbon::now()->subDays(30)->format('Y-m-d'));
        $endDate = session('activity_customer_end_date', Carbon::now()->format('Y-m-d'));

        return [
            'start' => Carbon::parse($startDate),
            'end' => Carbon::parse($endDate),
        ];
    }

    /**
     * Get the selected customer filter (from session or null)
     */
    protected function getSelectedCustomerFilter()
    {
        return session('activity_customer_customer_filter', null);
    }

    /**
     * Create a selector card for date range and customer filter
     */
    protected function selectorCard()
    {
        $dateRange = $this->getSelectedDateRange();
        $selectedCustomerFilter = $this->getSelectedCustomerFilter();

        return (new HtmlCard)
            ->width('full')
            ->view('activity-customer-selector', [
                'startDate' => $dateRange['start']->format('Y-m-d'),
                'endDate' => $dateRange['end']->format('Y-m-d'),
                'selectedCustomerFilter' => $selectedCustomerFilter,
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
     * Get all activities aggregated by customer (creator_id)
     */
    protected function getActivitiesByCustomer(Carbon $startDate, Carbon $endDate, $customerFilter = null)
    {
        $activities = UsersStoriesLog::whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->where('elapsed_minutes', '>', 0)
            ->with(['story.creator'])
            ->get();

        // Group by customer (creator_id)
        $groupedByCustomer = collect();
        
        foreach ($activities as $activity) {
            $story = $activity->story;
            if ($story && $story->creator_id) {
                $creator = $story->creator;
                $customerId = $story->creator_id;
                $customerName = $creator ? $creator->name : 'No Customer';
                
                // Apply customer filter if specified (LIKE search)
                if ($customerFilter && strpos(strtolower($customerName), strtolower($customerFilter)) === false) {
                    continue;
                }
                
                if (!$groupedByCustomer->has($customerId)) {
                    $groupedByCustomer[$customerId] = collect();
                }
                $groupedByCustomer[$customerId]->push([
                    'activity' => $activity,
                    'customer_name' => $customerName,
                ]);
            } else {
                // If no creator, group under "No Customer"
                $customerId = 0;
                $customerName = 'No Customer';
                
                // Apply customer filter if specified
                if ($customerFilter && strpos(strtolower($customerName), strtolower($customerFilter)) === false) {
                    continue;
                }
                
                if (!$groupedByCustomer->has($customerId)) {
                    $groupedByCustomer[$customerId] = collect();
                }
                $groupedByCustomer[$customerId]->push([
                    'activity' => $activity,
                    'customer_name' => $customerName,
                ]);
            }
        }

        return $groupedByCustomer;
    }

    /**
     * Create a card for activity table
     */
    protected function activityTableCard(Carbon $startDate, Carbon $endDate)
    {
        $customerFilter = $this->getSelectedCustomerFilter();
        
        $activities = UsersStoriesLog::whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->where('elapsed_minutes', '>', 0)
            ->with(['story.creator'])
            ->get();
            
        $groupedByCustomer = $this->getActivitiesByCustomer($startDate, $endDate, $customerFilter);

        // Calculate total for the period (based on filtered results)
        $customerStats = [];
        $allUniqueStories = collect();
        $allFilteredElapsedMinutes = [];
        
        foreach ($groupedByCustomer as $customerId => $customerActivities) {
            // Get unique story IDs for this customer
            $uniqueStories = $customerActivities->pluck('activity.story_id')->unique();
            
            // Sum all elapsed minutes for activities with this customer
            $customerTotalMinutes = $customerActivities->sum(function ($item) {
                return $item['activity']->elapsed_minutes;
            });
            
            // Get all elapsed minutes for min/max calculation
            $customerElapsedMinutes = $customerActivities->pluck('activity.elapsed_minutes')->toArray();
            
            $customerStats[$customerId] = [
                'name' => $customerActivities->first()['customer_name'],
                'total_minutes' => $customerTotalMinutes,
                'ticket_count' => $uniqueStories->count(),
                'elapsed_minutes' => $customerElapsedMinutes,
            ];
            
            // Collect unique stories and elapsed minutes for total calculation
            $allUniqueStories = $allUniqueStories->merge($uniqueStories);
            $allFilteredElapsedMinutes = array_merge($allFilteredElapsedMinutes, $customerElapsedMinutes);
        }
        
        // Total minutes: sum all filtered activities
        $totalMinutes = array_sum($allFilteredElapsedMinutes);
        // Total tickets: unique stories across filtered customers
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

        // Convert customerStats to array and sort alphabetically
        $customerStatsArray = array_values($customerStats);
        
        return (new HtmlCard)
            ->width('full')
            ->view('activity-customer-table', [
                'customerStats' => $customerStatsArray,
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
        return __('Customer');
    }

    /**
     * Get the URI key for the dashboard.
     *
     * @return string
     */
    public function uriKey()
    {
        return 'activity-customer';
    }
}


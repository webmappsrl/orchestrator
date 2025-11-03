<?php

namespace App\Nova\Dashboards;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\UsersStoriesLog;
use Illuminate\Support\Carbon;
use InteractionDesignFoundation\HtmlCard\HtmlCard;
use Laravel\Nova\Dashboard;

class ActivityCustomerDetails extends Dashboard
{
    /**
     * Get the selected date range (from session or default last 30 days)
     */
    protected function getSelectedDateRange()
    {
        $startDate = session('activity_customer_details_start_date', Carbon::now()->subDays(30)->format('Y-m-d'));
        $endDate = session('activity_customer_details_end_date', Carbon::now()->format('Y-m-d'));

        return [
            'start' => Carbon::parse($startDate),
            'end' => Carbon::parse($endDate),
        ];
    }

    /**
     * Get the selected customer ID (from session or null)
     */
    protected function getSelectedCustomerId()
    {
        return session('activity_customer_details_customer_id', null);
    }

    /**
     * Create a selector card for date range and customer
     */
    protected function selectorCard()
    {
        $dateRange = $this->getSelectedDateRange();
        $selectedCustomerId = $this->getSelectedCustomerId();

        // Get all users who have created stories and have Customer role
        $customers = User::whereHas('customerStories')
            ->whereJsonContains('roles', UserRole::Customer->value)
            ->orderBy('name')
            ->get();

        return (new HtmlCard)
            ->width('full')
            ->view('activity-customer-details-selector', [
                'startDate' => $dateRange['start']->format('Y-m-d'),
                'endDate' => $dateRange['end']->format('Y-m-d'),
                'selectedCustomerId' => $selectedCustomerId,
                'customers' => $customers,
            ])
            ->canSee(function ($request) {
                /** @var User $user */
                $user = $request->user();

                return $user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Manager);
            })
            ->center(true);
    }

    /**
     * Get tickets for the selected customer and date range
     */
    protected function getTicketsByCustomer(Carbon $startDate, Carbon $endDate, $customerId = null)
    {
        if (!$customerId) {
            return collect();
        }

        $activities = UsersStoriesLog::whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->where('elapsed_minutes', '>', 0)
            ->with(['story.creator', 'user'])
            ->get();

        // Filter activities by customer (creator_id) and get unique stories
        $stories = collect();
        $processedStoryIds = [];
        
        foreach ($activities as $activity) {
            $story = $activity->story;
            if ($story && $story->creator_id == $customerId) {
                if (!in_array($story->id, $processedStoryIds)) {
                    $processedStoryIds[] = $story->id;
                    
                    // Get the last activity date for this story in the date range
                    $lastActivity = UsersStoriesLog::where('story_id', $story->id)
                        ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                        ->orderBy('date', 'desc')
                        ->first();
                    
                    // Calculate total minutes for this story in the date range
                    $totalMinutes = UsersStoriesLog::where('story_id', $story->id)
                        ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                        ->sum('elapsed_minutes');
                    
                    $lastActivityDate = $lastActivity ? Carbon::parse($lastActivity->date) : null;
                    
                    $stories->push([
                        'story' => $story,
                        'last_activity_date' => $lastActivityDate,
                        'total_minutes' => $totalMinutes,
                    ]);
                }
            }
        }

        // Sort by last activity date descending (most recent first)
        return $stories->sortByDesc(function ($item) {
            return $item['last_activity_date'] ? $item['last_activity_date']->timestamp : 0;
        })->values();
    }

    /**
     * Create a card for tickets table
     */
    protected function ticketsTableCard(Carbon $startDate, Carbon $endDate)
    {
        $customerId = $this->getSelectedCustomerId();
        
        $tickets = $this->getTicketsByCustomer($startDate, $endDate, $customerId);
        $selectedCustomer = $customerId ? User::find($customerId) : null;

        // Calculate summary statistics
        $totalTickets = $tickets->count();
        $allMinutes = $tickets->pluck('total_minutes')->toArray();
        $totalMinutes = array_sum($allMinutes);
        $totalHours = floor($totalMinutes / 60);
        $totalMinutesRemainder = $totalMinutes % 60;
        
        // Calculate average time per ticket
        $avgMinutes = $totalTickets > 0 ? round($totalMinutes / $totalTickets) : 0;
        $avgHours = floor($avgMinutes / 60);
        $avgMinutesRemainder = $avgMinutes % 60;
        
        // Calculate min and max durations
        $minMinutes = !empty($allMinutes) ? min($allMinutes) : 0;
        $maxMinutes = !empty($allMinutes) ? max($allMinutes) : 0;
        $minHours = floor($minMinutes / 60);
        $minMinutesRemainder = $minMinutes % 60;
        $maxHours = floor($maxMinutes / 60);
        $maxMinutesRemainder = $maxMinutes % 60;

        return (new HtmlCard)
            ->width('full')
            ->view('activity-customer-details-table', [
                'tickets' => $tickets,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'selectedCustomer' => $selectedCustomer,
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
            $this->ticketsTableCard($dateRange['start'], $dateRange['end']),
        ];
    }

    /**
     * Get the displayable name of the dashboard.
     *
     * @return string
     */
    public function name()
    {
        return __('Activity Customer Details');
    }

    /**
     * Get the URI key for the dashboard.
     *
     * @return string
     */
    public function uriKey()
    {
        return 'activity-customer-details';
    }
}


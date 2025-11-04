<?php

namespace App\Nova\Dashboards;

use App\Enums\UserRole;
use App\Models\Tag;
use App\Models\User;
use App\Models\UsersStoriesLog;
use Illuminate\Support\Carbon;
use InteractionDesignFoundation\HtmlCard\HtmlCard;
use Laravel\Nova\Dashboard;

class ActivityTags extends Dashboard
{
    /**
     * Get the selected date range (from session or default last 30 days)
     */
    protected function getSelectedDateRange()
    {
        $startDate = session('activity_tags_start_date', Carbon::now()->subDays(30)->format('Y-m-d'));
        $endDate = session('activity_tags_end_date', Carbon::now()->format('Y-m-d'));

        return [
            'start' => Carbon::parse($startDate),
            'end' => Carbon::parse($endDate),
        ];
    }

    /**
     * Get the selected tag filter (from session or null)
     */
    protected function getSelectedTagFilter()
    {
        return session('activity_tags_tag_filter', null);
    }

    /**
     * Create a selector card for date range and tag filter
     */
    protected function selectorCard()
    {
        $dateRange = $this->getSelectedDateRange();
        $selectedTagFilter = $this->getSelectedTagFilter();

        return (new HtmlCard)
            ->width('full')
            ->view('activity-tags-selector', [
                'startDate' => $dateRange['start']->format('Y-m-d'),
                'endDate' => $dateRange['end']->format('Y-m-d'),
                'selectedTagFilter' => $selectedTagFilter,
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
     * Get all activities aggregated by tag
     */
    protected function getActivitiesByTag(Carbon $startDate, Carbon $endDate, $tagFilter = null)
    {
        $activities = UsersStoriesLog::whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->where('elapsed_minutes', '>', 0)
            ->with(['story.tags'])
            ->get();

        // Group by tag - each activity can have multiple tags, so we need to expand
        $groupedByTag = collect();
        
        foreach ($activities as $activity) {
            $story = $activity->story;
            if ($story && $story->tags) {
                $tags = $story->tags;
                if ($tags->isEmpty()) {
                    // If no tags, group under "No Tag"
                    $tagId = 0;
                    $tagName = 'No Tag';
                    if (!$groupedByTag->has($tagId)) {
                        $groupedByTag[$tagId] = collect();
                    }
                    $groupedByTag[$tagId]->push([
                        'activity' => $activity,
                        'tag_name' => $tagName,
                    ]);
                } else {
                    // Add activity to each tag
                    foreach ($tags as $tag) {
                        $tagId = $tag->id;
                        $tagName = $tag->name;
                        
                        // Apply tag filter if specified (LIKE search)
                        if ($tagFilter && strpos(strtolower($tagName), strtolower($tagFilter)) === false) {
                            continue;
                        }
                        
                        if (!$groupedByTag->has($tagId)) {
                            $groupedByTag[$tagId] = collect();
                        }
                        $groupedByTag[$tagId]->push([
                            'activity' => $activity,
                            'tag_name' => $tagName,
                        ]);
                    }
                }
            }
        }

        return $groupedByTag;
    }

    /**
     * Create a card for activity table
     */
    protected function activityTableCard(Carbon $startDate, Carbon $endDate)
    {
        $tagFilter = $this->getSelectedTagFilter();
        
        $activities = UsersStoriesLog::whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->where('elapsed_minutes', '>', 0)
            ->with(['story.tags'])
            ->get();
            
        $groupedByTag = $this->getActivitiesByTag($startDate, $endDate, $tagFilter);

        // Calculate total for the period (based on filtered results)
        $tagStats = [];
        $allUniqueStories = collect();
        $allFilteredElapsedMinutes = [];
        
        foreach ($groupedByTag as $tagId => $tagActivities) {
            // Get unique story IDs for this tag
            $uniqueStories = $tagActivities->pluck('activity.story_id')->unique();
            
            // Sum all elapsed minutes for activities with this tag
            $tagTotalMinutes = $tagActivities->sum(function ($item) {
                return $item['activity']->elapsed_minutes;
            });
            
            // Get all elapsed minutes for min/max calculation
            $tagElapsedMinutes = $tagActivities->pluck('activity.elapsed_minutes')->toArray();
            
            $tagStats[$tagId] = [
                'name' => $tagActivities->first()['tag_name'],
                'total_minutes' => $tagTotalMinutes,
                'ticket_count' => $uniqueStories->count(),
                'elapsed_minutes' => $tagElapsedMinutes,
            ];
            
            // Collect unique stories and elapsed minutes for total calculation
            $allUniqueStories = $allUniqueStories->merge($uniqueStories);
            $allFilteredElapsedMinutes = array_merge($allFilteredElapsedMinutes, $tagElapsedMinutes);
        }
        
        // Total minutes: sum all filtered activities
        $totalMinutes = array_sum($allFilteredElapsedMinutes);
        // Total tickets: unique stories across filtered tags
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

        // Convert tagStats to array and remove the PHP sorting so JS can sort
        $tagStatsArray = array_values($tagStats);
        
        return (new HtmlCard)
            ->width('full')
            ->view('activity-tags-table', [
                'tagStats' => $tagStatsArray,
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
        return __('Tags');
    }

    /**
     * Get the URI key for the dashboard.
     *
     * @return string
     */
    public function uriKey()
    {
        return 'activity-tags';
    }
}


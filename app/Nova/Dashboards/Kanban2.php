<?php

namespace App\Nova\Dashboards;

use App\Enums\StoryStatus;
use App\Enums\StoryType;
use App\Enums\UserRole;
use App\Models\Story;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use InteractionDesignFoundation\HtmlCard\HtmlCard;
use Laravel\Nova\Dashboard as Dashboard;

class Kanban2 extends Dashboard
{
    /*---Helper---*/
    protected function byStatusAndUser(string $status, Authenticatable $user)
    {
        return Story::query()
            ->where('status', $status)
            ->whereNotNull('user_id')
            ->whereNotNull('type')
            ->where('type', '!=', StoryType::Scrum->value)
            ->when(in_array($status, [
                StoryStatus::Todo->value,
                StoryStatus::Progress->value,
                StoryStatus::Tested->value,
                StoryStatus::Waiting->value,
                StoryStatus::Problem->value,
            ]), function ($q) use ($user) {
                return $q->where('user_id', $user->id);
            })
            ->when(in_array($status, [
                StoryStatus::Test->value,
            ]), function ($q) use ($user) {
                return $q->where('tester_id', $user->id);
            })
            ->with(['creator', 'tags', 'tester', 'user']) // Eager load relationships to avoid N+1 queries
            ->get();
    }

    protected function byStatusAndUserAsDeveloper(string $status, Authenticatable $user)
    {
        return Story::query()
            ->where('status', $status)
            ->where('user_id', $user->id)
            ->whereNotNull('type')
            ->where('type', '!=', StoryType::Scrum->value)
            ->with(['creator', 'tags', 'tester', 'user'])
            ->get();
    }

    protected function storyCard(string $status, string $label, $user, $width = 'full', $customTitle = null, $filterByDeveloper = false, $showTester = false, $showAssignedTo = false)
    {
        if ($filterByDeveloper) {
            $stories = $this->byStatusAndUserAsDeveloper($status, $user);
        } else {
            $stories = $this->byStatusAndUser($status, $user);
        }

        // Aggiungi il conteggio al titolo
        $count = $stories->count();
        $title = ($customTitle ?? $label) . ': [' . $count . '] ticket';

        return (new HtmlCard)
            ->width($width)
            ->view('story-viewer-kanban2', [
                'stories' => $stories,
                'statusLabel' => $title,
                'showTester' => $showTester,
                'showAssignedTo' => $showAssignedTo,
                'status' => $status,
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
     * Get stories with status 'todo' and 'assigned' for a user, ordered by status (todo first, then assigned)
     */
    protected function getTodoAndAssignedStories(Authenticatable $user)
    {
        $stories = Story::query()
            ->whereIn('status', [StoryStatus::Todo->value, StoryStatus::Assigned->value])
            ->where('user_id', $user->id)
            ->whereNotNull('type')
            ->where('type', '!=', StoryType::Scrum->value)
            ->with(['creator', 'tags', 'tester', 'user'])
            ->get()
            ->sortBy(function ($story) {
                // Sort: todo first (value 1), then assigned (value 2)
                return $story->status === StoryStatus::Todo->value ? 1 : 2;
            })
            ->values();

        return $stories;
    }

    /**
     * Create a card for TODO table with both todo and assigned statuses
     */
    protected function todoAndAssignedCard($user, $width = 'full')
    {
        $stories = $this->getTodoAndAssignedStories($user);
        $count = $stories->count();
        $title = 'Cosa devo fare (todo/assigned): [' . $count . '] ticket';

        return (new HtmlCard)
            ->width($width)
            ->view('story-viewer-kanban2', [
                'stories' => $stories,
                'statusLabel' => $title,
                'showTester' => false,
                'showAssignedTo' => false,
                'status' => 'todo-assigned', // Special status to indicate multiple statuses
                'showStatusColumn' => true, // Flag to show status column
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
     * Get recent activities for a user (last 2 days with activity)
     * Only includes stories with status Released or Done
     */
    protected function getRecentActivities(Authenticatable $user)
    {
        // Find the most recent date with activity for this user
        $mostRecentActivity = \App\Models\UsersStoriesLog::where('user_id', $user->id)
            ->where('elapsed_minutes', '>', 0)
            ->whereHas('story', function ($query) {
                $query->whereIn('status', [StoryStatus::Released->value, StoryStatus::Done->value]);
            })
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->first();
        
        if (!$mostRecentActivity) {
            return collect();
        }
        
        // Get all distinct dates with activity, ordered by date descending
        $uniqueDates = \App\Models\UsersStoriesLog::where('user_id', $user->id)
            ->where('elapsed_minutes', '>', 0)
            ->whereHas('story', function ($query) {
                $query->whereIn('status', [StoryStatus::Released->value, StoryStatus::Done->value]);
            })
            ->distinct()
            ->orderBy('date', 'desc')
            ->pluck('date')
            ->take(2); // Take the last 2 days
        
        if ($uniqueDates->isEmpty()) {
            return collect();
        }
        
        // Get all activities from those dates, filtered by status Released or Done
        return \App\Models\UsersStoriesLog::where('user_id', $user->id)
            ->whereIn('date', $uniqueDates)
            ->where('elapsed_minutes', '>', 0)
            ->whereHas('story', function ($query) {
                $query->whereIn('status', [StoryStatus::Released->value, StoryStatus::Done->value]);
            })
            ->with(['story.tags', 'story.creator'])
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('story_id')
            ->map(function ($group) {
                // Get the most recent entry for each story
                return $group->first();
            })
            ->values();
    }

    /**
     * Create a card for recent activities
     */
    protected function recentActivitiesCard(Authenticatable $user)
    {
        $activities = $this->getRecentActivities($user);
        $count = $activities->count();
        $title = 'Cosa ho fatto ieri?: [' . $count . '] ticket';

        return (new HtmlCard)
            ->width('full')
            ->view('story-viewer-recent-activities', [
                'activities' => $activities,
                'statusLabel' => $title,
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
     * Get experimental activities based on released_at and done_at dates
     * Shows tickets from last 2 days (excluding today) starting from the last day with at least one ticket
     */
    protected function getExperimentalActivities(Authenticatable $user)
    {
        $today = \Carbon\Carbon::today();
        
        // Find the most recent date (excluding today) with at least one ticket that has released_at or done_at
        $mostRecentStory = Story::where('user_id', $user->id)
            ->whereNotNull('type')
            ->where('type', '!=', StoryType::Scrum->value)
            ->where(function ($query) use ($today) {
                $query->where(function ($q) use ($today) {
                    $q->whereNotNull('released_at')
                      ->whereDate('released_at', '<', $today);
                })
                ->orWhere(function ($q) use ($today) {
                    $q->whereNotNull('done_at')
                      ->whereDate('done_at', '<', $today);
                });
            })
            ->orderByRaw('COALESCE(released_at, done_at) DESC')
            ->first();
        
        if (!$mostRecentStory) {
            return collect();
        }
        
        // Get the date from the most recent story (released_at or done_at)
        $mostRecentDate = $mostRecentStory->released_at ?? $mostRecentStory->done_at;
        $mostRecentDateOnly = $mostRecentDate->copy()->startOfDay();
        
        // Calculate the date range: last 2 days starting from mostRecentDateOnly (excluding today)
        $endDate = $mostRecentDateOnly->copy();
        $startDate = $endDate->copy()->subDay(); // Last 2 days: today - 1 and today - 2
        
        // Get all stories with released_at or done_at in the date range
        return Story::where('user_id', $user->id)
            ->whereNotNull('type')
            ->where('type', '!=', StoryType::Scrum->value)
            ->where(function ($query) use ($startDate, $endDate) {
                $query->where(function ($q) use ($startDate, $endDate) {
                    $q->whereNotNull('released_at')
                      ->whereDate('released_at', '>=', $startDate)
                      ->whereDate('released_at', '<=', $endDate);
                })
                ->orWhere(function ($q) use ($startDate, $endDate) {
                    $q->whereNotNull('done_at')
                      ->whereDate('done_at', '>=', $startDate)
                      ->whereDate('done_at', '<=', $endDate);
                });
            })
            ->with(['tags', 'creator', 'user'])
            ->get()
            ->sortByDesc(function ($story) {
                // Sort by released_at or done_at (whichever is more recent)
                $releasedAt = $story->released_at ? $story->released_at->timestamp : 0;
                $doneAt = $story->done_at ? $story->done_at->timestamp : 0;
                return max($releasedAt, $doneAt);
            })
            ->values();
    }

    /**
     * Create a card for experimental activities
     */
    protected function experimentalActivitiesCard(Authenticatable $user)
    {
        $stories = $this->getExperimentalActivities($user);
        $count = $stories->count();
        $title = 'Che cosa ho fatto ieri (sperimentale)?: [' . $count . '] ticket';

        return (new HtmlCard)
            ->width('full')
            ->view('story-viewer-experimental-activities', [
                'stories' => $stories,
                'statusLabel' => $title,
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
     * Create a developer selector card for admins and developers
     */
    protected function developerSelectorCard()
    {
        $developers = User::whereJsonContains('roles', UserRole::Developer->value)
            ->orderBy('name')
            ->get();

        return (new HtmlCard)
            ->width('full')
            ->view('developer-selector', [
                'developers' => $developers,
                'currentUser' => auth()->user(),
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
     * Get the selected user (from query parameter or current user)
     */
    protected function getSelectedUser()
    {
        /** @var User $currentUser */
        $currentUser = auth()->user();
        $selectedDeveloperId = session('selected_developer_id');

        if ($selectedDeveloperId) {
            $selectedDeveloper = User::find($selectedDeveloperId);

            if ($selectedDeveloper && $selectedDeveloper->hasRole(UserRole::Developer)) {
                return $selectedDeveloper;
            }
        }

        return $currentUser;
    }

    /**
     * Get the cards for the dashboard.
     *
     * @return array
     */
    public function cards()
    {
        $user = $this->getSelectedUser();
        /** @var User $currentUser */
        $currentUser = auth()->user();

        $cards = [];

        // Aggiungi il selettore developer per admin e developer
        if ($currentUser->hasRole(UserRole::Admin) || $currentUser->hasRole(UserRole::Developer)) {
            $cards[] = $this->developerSelectorCard();
        }

        // Aggiungi la tabella attività recenti
        $cards[] = $this->recentActivitiesCard($user);

        // Aggiungi la tabella attività sperimentali (basata su released_at e done_at)
        $cards[] = $this->experimentalActivitiesCard($user);

        // Aggiungi la tabella Test assegnate come developer (in attesa di verifica)
        $cards[] = $this->storyCard('testing', __('Test'), $user, 'full', 'In attesa di verifica (da testare)', true, true);

        // Aggiungi la tabella Problem
        $cards[] = $this->storyCard('problem', __('Problem'), $user, 'full', 'Che problemi ho incontrato (problemi)');

        // Aggiungi la tabella Waiting
        $cards[] = $this->storyCard('waiting', __('Waiting'), $user, 'full', 'Cosa sto aspettando (in attesa)');

        // Aggiungi la tabella TODO (con todo e assigned)
        $cards[] = $this->todoAndAssignedCard($user);

        // Aggiungi la tabella Test (ticket assegnati come tester)
        $cards[] = $this->storyCard('testing', __('Test'), $user, 'full', 'Cosa devo verificare (da testare)', false, false, true);

        return $cards;
    }

    /**
     * Get the displayable name of the dashboard.
     *
     * @return string
     */
    public function name()
    {
        return 'KANBAN-2';
    }

    /**
     * Get the URI key for the dashboard.
     *
     * @return string
     */
    public function uriKey()
    {
        return 'kanban-2';
    }
}


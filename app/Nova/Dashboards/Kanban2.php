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
            ]), function ($q) use ($user) {
                return $q->where('user_id', $user->id);
            })
            ->when(in_array($status, [
                StoryStatus::Test->value,
            ]), function ($q) use ($user) {
                return $q->where('tester_id', $user->id);
            })
            ->with(['creator', 'tags']) // Eager load relationships to avoid N+1 queries
            ->get();
    }

    protected function byStatusAndUserAsDeveloper(string $status, Authenticatable $user)
    {
        return Story::query()
            ->where('status', $status)
            ->where('user_id', $user->id)
            ->whereNotNull('type')
            ->where('type', '!=', StoryType::Scrum->value)
            ->with(['creator', 'tags'])
            ->get();
    }

    protected function storyCard(string $status, string $label, $user, $width = 'full', $customTitle = null, $filterByDeveloper = false)
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
            ])
            ->canSee(function ($request) {
                /** @var User $user */
                $user = $request->user();

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

        // Aggiungi la tabella Test assegnate come developer (in attesa di verifica)
        $cards[] = $this->storyCard('testing', __('Test'), $user, 'full', 'In attesa di verifica (da testare)', true);

        // Aggiungi la tabella Waiting
        $cards[] = $this->storyCard('waiting', __('Waiting'), $user, 'full', 'Che problemi ho incontrato (in attesa)');

        // Aggiungi la tabella TODO
        $cards[] = $this->storyCard('todo', __('To Do'), $user, 'full', 'Cosa devo fare oggi (todo)');

        // Aggiungi la tabella Test (ticket assegnati come tester)
        $cards[] = $this->storyCard('testing', __('Test'), $user, 'full', 'Cosa devo verificare (da testare)');

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


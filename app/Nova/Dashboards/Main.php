<?php

namespace App\Nova\Dashboards;

use App\Enums\StoryStatus;
use App\Enums\StoryType;
use App\Enums\UserRole;
use App\Models\Story;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use InteractionDesignFoundation\HtmlCard\HtmlCard;
use Laravel\Nova\Dashboards\Main as Dashboard;

class Main extends Dashboard
{
    public function name()
    {
        return 'kanban';
    }

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
            ->get();
    }

    protected function storyCard(string $status, string $label, $user, $width = 'full')
    {
        $stories = $this->byStatusAndUser($status, $user);

        return (new HtmlCard)
            ->width($width)
            ->view('story-viewer', [
                'stories' => $stories,
                'statusLabel' => $label,
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

        // Aggiungi le carte delle storie
        $cards = array_merge($cards, [
            $this->storyCard('progress', __('Progress'), $user),
            $this->storyCard('todo', __('To Do'), $user),
            $this->storyCard('testing', __('Test'), $user, '1/2'),
            $this->storyCard('tested', __('Tested'), $user, '1/2'),
        ]);

        return $cards;
    }
}

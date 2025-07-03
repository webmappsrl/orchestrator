<?php

namespace App\Nova\Dashboards;

use App\Models\Story;
use App\Enums\StoryType;
use App\Enums\UserRole;
use App\Enums\StoryStatus;
use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Nova\Dashboards\Main as Dashboard;
use InteractionDesignFoundation\HtmlCard\HtmlCard;

class Main extends Dashboard
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
                $user = $request->user();
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
        $user = auth()->user();

        return [
            $this->storyCard('progress', __('Progress'), $user),
            $this->storyCard('todo', __('To Do'), $user),
            $this->storyCard('testing', __('Test'), $user, '1/2'),
            $this->storyCard('tested', __('Tested'), $user, '1/2'),
        ];
    }
}

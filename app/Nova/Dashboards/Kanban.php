<?php

namespace App\Nova\Dashboards;

use App\Enums\StoryStatus;
use App\Enums\UserRole;
use App\Models\Story;
use App\Models\User;
use Laravel\Nova\Dashboard as Dashboard;
use Webmapp\KanbanCard\KanbanCard;

class Kanban extends Dashboard
{
    /**
     * Get the cards for the dashboard.
     *
     * @return array
     */
    public function cards()
    {
        /** @var User $currentUser */
        $currentUser = auth()->user();

        return [
            (new KanbanCard)
            ->model(Story::class, 'status')
            ->resourceUri('stories')
            ->with(['user', 'tester'])
            ->title('name')
            ->subtitle('user.name')
            ->displayFields([
                'user.name' => __('Assigned'),
                'tester.name' => __('Tester'),
            ])
            ->filterAndSearchBy(
                'user_id',
                User::class,
                'name',
                ['name', 'user.name', 'tester.name'],
                function ($q) {
                    return $q->whereHas('stories')
                        ->where(function ($query) {
                            return $query
                                ->whereJsonContains('roles', UserRole::Admin->value)
                                ->orWhereJsonContains('roles', UserRole::Developer->value)
                                ->orWhereJsonContains('roles', UserRole::Manager->value);
                        });
                }
            )
            ->initialFilterValue((string) ($currentUser ? $currentUser->id : ''))
            ->toolbarTitle(__('Kanban View'))
            ->toolbarLabel(__('View kanban for:'))
            ->statusFilterOverrides([
                StoryStatus::Test->value => 'tester_id',
                StoryStatus::Tested->value => ['tester_id', 'user_id'],
            ])
            ->statusColumnLimits([
                StoryStatus::Progress->value => 1,
            ])
            ->showFilterAll(false)
            ->deniedToUpdateStatusForRoles([UserRole::Customer])
            ->allowedToUpdateStatusForRoles([UserRole::Admin, UserRole::Developer])
            ->limitPerColumn(20)
            ->columns(
                array_map(
                    fn (StoryStatus $status) => [
                        'value' => $status->value,
                        'label' => $status->label(),
                        'color' => $status->color() ?: KanbanCard::DEFAULT_COLOR,
                    ],
                    [
                        StoryStatus::Todo,
                        StoryStatus::Progress,
                        StoryStatus::Waiting,
                        StoryStatus::Test,
                        StoryStatus::Tested,
                    ]
                )
            )
            ->canSee(function ($request) {
                /** @var User $viewer */
                $viewer = $request->user();

                return $viewer->hasRole(UserRole::Admin) || $viewer->hasRole(UserRole::Developer);
            }),
        ];
    }
}

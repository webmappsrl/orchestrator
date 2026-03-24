<?php

namespace App\Nova\Dashboards;

use App\Enums\StoryStatus;
use App\Enums\StoryType;
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
                ->with(['user', 'tester', 'tags', 'creator'])
                ->title('name')
                ->googleCalendarTitleFormat(true)
                ->displayFields([
                    'creator.name' => __('Creator'),
                    'user.name' => __('Assigned'),
                    'tester.name' => __('Tester'),
                    'type' => __('Type'),
                    'tags.*.name' => __('Tags'),
                    'created_at' => __('Created At'),
                    'description' => __('Description'),
                ])
                ->filterBy(
                    'user_id',
                    User::class,
                    'name',
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
                ->selectOnly(true)
                ->priorityField('priority')
                ->enableIntraColumnReorder(true)
                ->statusFilterOverrides([
                    StoryStatus::Test->value => 'tester_id',
                    StoryStatus::Tested->value => 'tester_id',
                    StoryStatus::Released->value => ['user_id', 'creator_id'],
                ])
                ->statusColumnLimits([
                    StoryStatus::Progress->value => 1,
                ])
                ->excludeFieldValues('type', [StoryType::Scrum->value])
                ->showFilterAll(false)
                ->deniedToUpdateStatusForRoles([UserRole::Customer])
                ->allowedToUpdateStatusForRoles([UserRole::Admin, UserRole::Developer])
                ->limitPerColumn(20)
                ->columns(
                    array_merge(
                        array_map(
                            fn(StoryStatus $status) => [
                                'value' => $status->value,
                                'label' => $status->label(),
                                'color' => $status->color() ?: KanbanCard::DEFAULT_COLOR,
                            ],
                            [
                                StoryStatus::Assigned,
                                StoryStatus::Todo,
                                StoryStatus::Progress,
                                StoryStatus::Waiting,
                                StoryStatus::Test,
                                StoryStatus::Tested,
                            ]
                        ),
                        [[
                            'value' => 'tested_by_others',
                            'label' => __('Has Been Tested'),
                            'color' => '#86EFAC',
                        ]],
                        array_map(
                            fn(StoryStatus $status) => [
                                'value' => $status->value,
                                'label' => $status->label(),
                                'color' => $status->color() ?: KanbanCard::DEFAULT_COLOR,
                            ],
                            [StoryStatus::Released]
                        )
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

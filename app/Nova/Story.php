<?php

namespace App\Nova;


use App\Models\Epic;
use App\Models\User;
use App\Models\Project;
use Laravel\Nova\Panel;
use App\Models\Deadline;
use App\Enums\StoryStatus;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Status;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\MorphToMany;
use Laravel\Nova\Fields\MultiSelect;
use App\Nova\Actions\MoveStoriesFromEpic;
use Datomatic\NovaMarkdownTui\MarkdownTui;
use Laravel\Nova\Http\Requests\NovaRequest;
use Datomatic\NovaMarkdownTui\Enums\EditorType;
use App\Nova\Actions\moveStoriesFromProjectToEpicAction;
use Carbon\Carbon;

class Story extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\Story>
     */
    public static $model = \App\Models\Story::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'name';

    /**
     * The number of resources to show per page via relationships.
     *
     * @var int
     */
    public static $perPageViaRelationship = 20;



    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id', 'name', 'description'
    ];



    /**
     * Get the fields displayed by the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [
            ID::make()->sortable(),
            Text::make(__('Name'), 'name')->sortable()
                ->displayUsing(function ($name, $a, $b) {
                    $wrappedName = wordwrap($name, 75, "\n", true);
                    $htmlName = str_replace("\n", '<br>', $wrappedName);
                    return $htmlName;
                })
                ->asHtml(),
            Select::make(__('Status'), 'status')->options([
                'New' => StoryStatus::New,
                'In Progress' => StoryStatus::Progress,
                'Done' => StoryStatus::Done,
                'Test' => StoryStatus::Test,
                'Rejected' => StoryStatus::Rejected,
            ])->onlyOnForms()
                ->default('New'),
            Status::make('Status')
                ->loadingWhen(['status' => 'progress'])
                ->failedWhen(['status' => 'rejected'])
                ->sortable(),
            //create a field to show all the name of deadlines and related customer name
            Text::make(__('Deadlines'), function () {
                $deadlines = $this->deadlines;
                $deadlineNames = [];
                foreach ($deadlines as $deadline) {
                    $dueDate = Carbon::parse($deadline->due_date)->format('d-m-Y');
                    if (isset($deadline->customer)) {
                        array_push($deadlineNames, $dueDate . ' (' . $deadline->customer->name . ')');
                    } else {
                        array_push($deadlineNames, $dueDate);
                    }
                }
                return implode('<br/> ', $deadlineNames);
            })->asHtml()->onlyOnIndex(),
            MarkdownTui::make(__('Description'), 'description')
                ->hideFromIndex()
                ->initialEditType(EditorType::MARKDOWN),
            BelongsTo::make('User')
                ->default(function ($request) {
                    $epic = Epic::find($request->input('viaResourceId'));
                    return $epic ? $epic->user_id : null;
                }),
            BelongsTo::make('Epic')
                ->nullable()
                ->default(function ($request) {
                    //handling the cases when the story is created from the epic page. Will no longer need when the create policy will be fixed.(create epic only from project)
                    $fromEpic =
                        $request->input('viaResource') === 'epics' ||
                        $request->input('viaResource') === 'new-epics' ||
                        $request->input('viaResource') === 'project-epics' ||
                        $request->input('viaResource') === 'progress-epics' ||
                        $request->input('viaResource') === 'test-epics' ||
                        $request->input('viaResource') === 'done-epics' ||
                        $request->input('viaResource') === 'rejected-epics';

                    if ($fromEpic) {
                        $epic = Epic::find($request->input('viaResourceId'));
                        return $epic->id;
                    } else {
                        return null;
                    }
                })
                ->hideFromIndex(),
            BelongsTo::make('Project')
                ->default(function ($request) {
                    //handling the cases when the story is created from the epic page. Will no longer need when the create policy will be fixed.(create epic only from project)
                    $fromEpic =
                        $request->input('viaResource') === 'epics' ||
                        $request->input('viaResource') === 'new-epics' ||
                        $request->input('viaResource') === 'project-epics' ||
                        $request->input('viaResource') === 'progress-epics' ||
                        $request->input('viaResource') === 'test-epics' ||
                        $request->input('viaResource') === 'done-epics' ||
                        $request->input('viaResource') === 'rejected-epics';
                    if ($fromEpic) {
                        $epic = Epic::find($request->input('viaResourceId'));
                        $project = Project::find($epic->project_id);
                        return $project ? $project->id : null;
                    }
                })
                ->searchable()
                ->hideFromIndex(),
            MorphToMany::make('Deadlines'),
            //add a panel to show the related epic description
            new Panel(__('Epic Description'), [
                MarkdownTui::make(__('Description'), 'epic.description')
                    ->hideFromIndex()
                    ->initialEditType(EditorType::MARKDOWN)
                    ->onlyOnDetail()
            ]),
        ];
    }
    /**
     * Get the cards available for the request.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function cards(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the filters available for the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function filters(NovaRequest $request)
    {
        return [
            new filters\UserFilter,
            new filters\StoryStatusFilter,
        ];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function lenses(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function actions(NovaRequest $request)
    {
        $actions = [
            (new actions\EditStoriesFromEpic)
                ->confirmText('Edit Status, User and Deadline for the selected stories. Click "Confirm" to save or "Cancel" to delete.')
                ->confirmButtonText('Confirm')
                ->cancelButtonText('Cancel'),

            (new actions\StoryToProgressStatusAction)
                ->onlyInline()
                ->confirmText('Click on the "Confirm" button to save the status in Progress or "Cancel" to cancel.')
                ->confirmButtonText('Confirm')
                ->cancelButtonText('Cancel'),

            (new actions\StoryToDoneStatusAction)
                ->onlyInline()
                ->confirmText('Click on the "Confirm" button to save the status in Done or "Cancel" to cancel.')
                ->confirmButtonText('Confirm')
                ->cancelButtonText('Cancel'),

            (new actions\StoryToTestStatusAction)
                ->onlyInline()
                ->confirmText('Click on the "Confirm" button to save the status in Test or "Cancel" to cancel.')
                ->confirmButtonText('Confirm')
                ->cancelButtonText('Cancel'),

            (new actions\StoryToRejectedStatusAction)
                ->onlyInline()
                ->confirmText('Click on the "Confirm" button to save the status in Rejected or "Cancel" to cancel.')
                ->confirmButtonText('Confirm')
                ->cancelButtonText('Cancel'),
        ];

        if ($request->viaResource == 'projects') {
            array_push($actions, (new moveStoriesFromProjectToEpicAction)
                ->confirmText('Select the epic where you want to move the story. Click on "Confirm" to perform the action or "Cancel" to delete.')
                ->confirmButtonText('Confirm')
                ->cancelButtonText('Cancel'));
            array_push($actions, (new actions\createNewEpicFromStoriesAction)
                ->confirmText('Click on the "Confirm" button to create a new epic with selected stories or "Cancel" to cancel.')
                ->confirmButtonText('Confirm')
                ->cancelButtonText('Cancel'));
        }

        if ($request->viaResource != 'projects') {
            array_push($actions, (new actions\moveToBacklogAction)
                ->confirmText('Click on the "Confirm" button to move the selected stories to Backlog or "Cancel" to cancel.')
                ->confirmButtonText('Confirm')
                ->cancelButtonText('Cancel')
                ->showInline());
            array_push($actions, (new MoveStoriesFromEpic)
                ->confirmText('Select the epic where you want to move the story. Click on "Confirm" to perform the action or "Cancel" to delete.')
                ->confirmButtonText('Confirm')
                ->cancelButtonText('Cancel'));
        }

        return $actions;
    }



    public function indexBreadcrumb()
    {
        return null;
    }
}
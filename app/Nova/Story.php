<?php

namespace App\Nova;

use Carbon\Carbon;
use App\Models\Epic;
use App\Models\Project;
use Laravel\Nova\Panel;
use App\Enums\StoryType;
use App\Enums\StoryStatus;
use Laravel\Nova\Fields\ID;
use App\Enums\StoryPriority;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Status;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\MorphToMany;
use App\Nova\Actions\MoveStoriesFromEpic;
use Datomatic\NovaMarkdownTui\MarkdownTui;
use Laravel\Nova\Http\Requests\NovaRequest;
use Datomatic\NovaMarkdownTui\Enums\EditorType;
use Ebess\AdvancedNovaMediaLibrary\Fields\Files;
use App\Nova\Actions\moveStoriesFromProjectToEpicAction;

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
        $testDev = $this->test_dev;
        $testProd = $this->test_prod;

        return [
            new Panel(__('Navigate to the next or previous story'), $this->navigationLinks()),
            ID::make()->sortable(),
            Text::make(__('Name'), 'name')->sortable()
                ->displayUsing(function ($name, $a, $b) {
                    $wrappedName = wordwrap($name, 75, "\n", true);
                    $htmlName = str_replace("\n", '<br>', $wrappedName);
                    return $htmlName;
                })
                ->asHtml(),
            Text::make('Info', function () {
                $storyProject = $this->project;
                $storyProjectUrl = url('/resources/projects/' . $storyProject->id);
                $storyPriority = StoryPriority::getCase($this->priority);
                $storyStatus = $this->status;
                $storyType = $this->type;
                return '<a href="' . $storyProjectUrl . '" target="_blank" style="color:grey; font-weight:bold;">' . $storyProject->name . '</a>' . ' <br> ' . '<span style="color:' . ($this->priority == StoryPriority::Low->value ? 'green' : ($this->priority == StoryPriority::Medium->value ? 'orange' : 'red')) . '">' . $storyPriority . '</span>' . ' <br> ' . $storyStatus . ' <br> ' . '<span style="color:blue">' . $storyType . '</span>';
            })->asHtml()
                ->onlyOnIndex(),
            Select::make(('Status'), 'status')->options([
                'new' => StoryStatus::New,
                'progress' => StoryStatus::Progress,
                'done' => StoryStatus::Done,
                'testing' => StoryStatus::Test,
                'rejected' => StoryStatus::Rejected,
            ])->onlyOnForms()
                ->default('new'),
            Select::make('Priority', 'priority')->options([
                StoryPriority::Low->value => 'Low',
                StoryPriority::Medium->value => 'Medium',
                StoryPriority::High->value => 'High',
            ])->onlyOnForms()
                ->default($this->priority ?? StoryPriority::Low->value),
            Text::make('Priority')->displayUsing(function () {
                $color = 'red';
                $priority = '';
                if ($this->priority == StoryPriority::Low->value) {
                    $color = 'green';
                    $priority = 'Low';
                } elseif ($this->priority == StoryPriority::Medium->value) {
                    $color = 'orange';
                    $priority = 'Medium';
                } elseif ($this->priority == StoryPriority::High->value) {
                    $color = 'red';
                    $priority = 'High';
                }
                return '<span style="color:' . $color . '; font-weight: bold;">' . $priority . '</span>';
            })->asHtml()
                ->hideWhenCreating()
                ->hideWhenUpdating()
                ->sortable()
                ->hideFromIndex(),
            Status::make('Status')
                ->loadingWhen(['status' => 'new'])
                ->failedWhen(['status' => 'rejected'])
                ->sortable()
                ->onlyOnDetail(),
            Select::make(__('Type'), 'type')->options([
                'Bug' => StoryType::Bug,
                'Feature' => StoryType::Feature,
            ])->onlyOnForms()
                ->default('Feature'),
            Text::make('Type', function () {
                // color the type of the story and make it bold
                $type = $this->type;
                $color = 'blue';
                return '<span style="color:' . $color . '; font-weight: bold;">' . $type . '</span>';
            })->asHtml()
                ->hideWhenCreating()
                ->hideWhenUpdating()
                ->onlyOnDetail(),
            //create a field to show all the name of deadlines and related customer name
            Text::make(__('Deadlines'), function () {
                $deadlines = $this->deadlines;
                foreach ($deadlines as $deadline) {
                    $dueDate = Carbon::parse($deadline->due_date)->format('Y-m-d');
                    $deadlineTitle = $deadline->title ?? '';
                    $customerName = isset($deadline->customer) ? $deadline->customer->name : '';
                    $deadlineName = $dueDate . '<br/>' . $deadlineTitle . '<br/>' . $customerName;
                }
                return $deadlineName ?? '';
            })->asHtml()->onlyOnIndex(),
            MarkdownTui::make(__('Description'), 'description')
                ->hideFromIndex()
                ->initialEditType(EditorType::MARKDOWN),
            Textarea::make(__('Customer Request'), 'customer_request')
                ->hideFromIndex()
                ->alwaysShow(),
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
                ->nullable()
                ->hideFromIndex(),
            MorphToMany::make('Deadlines'),
            //add a panel to show the related epic description
            new Panel(__('Epic Description'), [
                MarkdownTui::make(__('Description'), 'epic.description')
                    ->hideFromIndex()
                    ->initialEditType(EditorType::MARKDOWN)
                    ->onlyOnDetail()
            ]),
            Files::make('Documents', 'documents')
                ->hideFromIndex(),

            $testDev !== null ? Text::make('DEV', function () use ($testDev) {
                $testDevLink = '<a style="color:green; font-weight:bold;" href="' . $testDev . '" target="_blank">' . '[X]' . '</a>';
                return $testDevLink;
            })->asHtml()
                ->hideWhenCreating()
                ->hideWhenUpdating() :
                Text::make('DEV', function () {
                    return '';
                })->asHtml()
                ->hideWhenCreating()
                ->hideWhenUpdating(),

            $testProd !== null ? Text::make('PROD', function () use ($testProd) {
                $testProdLink = '<a  style="color:green; font-weight:bold;" href="' . $testProd . '" target="_blank">' . '[X]' . '</a>';
                return $testProdLink;
            })->asHtml()
                ->hideWhenCreating()
                ->hideWhenUpdating() :
                Text::make('PROD', function () {
                    return '';
                })->asHtml()
                ->hideWhenCreating()
                ->hideWhenUpdating(),

            //make the text fields for the url visible in the form
            Text::make('Test Dev', 'test_dev')
                ->rules('nullable', 'url:http,https')
                ->onlyOnForms()
                ->help('Url must start with http or https'),
            Text::make('Test Prod', 'test_prod')
                ->rules('nullable', 'url:http,https')
                ->onlyOnForms()
                ->help('Url must start with http or https'),
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
            new filters\StoryTypeFilter
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
                ->showInline()
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

            (new actions\ConvertStoryToEpic)
                ->confirmText('Click on the "Confirm" button to convert the selected stories to epics or "Cancel" to cancel.')
                ->confirmButtonText('Confirm')
                ->cancelButtonText('Cancel')
                ->showInline(),
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

    public function navigationLinks()
    {
        return [
            Text::make('Navigate')->onlyOnDetail()->asHtml()->displayUsing(function () {
                $epic = Epic::find($this->epic_id);
                $stories = $epic->stories;
                $stories = $stories->sortBy('id');
                $stories = $stories->values();

                $currentStoryIndex = $stories->search(function ($story) {
                    return $story->id == $this->id;
                });

                $previousStory = $stories->get($currentStoryIndex - 1);
                $nextStory = $stories->get($currentStoryIndex + 1);

                $previousLink = '';
                $nextLink = '';

                if ($previousStory != null) {
                    $previousLink = '<a href="/resources/stories/' . $previousStory->id . '" style="font-size: 30px;">⬅️</a>';
                }

                if ($nextStory != null) {
                    $nextLink = '<a href="/resources/stories/' . $nextStory->id . '" style="font-size: 30px;">➡️</a>';
                }

                return $previousLink . '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . $nextLink;
            }),
        ];
    }
}

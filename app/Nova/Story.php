<?php

namespace App\Nova;

use Carbon\Carbon;
use App\Models\Epic;
use App\Enums\UserRole;
use App\Models\Project;
use Laravel\Nova\Panel;
use App\Enums\StoryType;
use Manogi\Tiptap\Tiptap;
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
use Ebess\AdvancedNovaMediaLibrary\Fields\Images;
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

    public static function indexQuery(NovaRequest $request, $query)
    {
        if ($request->user()->hasRole(UserRole::Customer)) {
            return $query->where('creator_id', $request->user()->id)->where('status', '!=', StoryStatus::Done);
        }
    }



    /**
     * Get the fields displayed by the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        $loggedUser = auth()->user();
        $options = $this->getOptions($loggedUser);
        $storyStatusOptions = $options;
        $testDev = $this->test_dev;
        $testProd = $this->test_prod;

        $tiptapAllButtons = [
            'heading',
            '|',
            'italic',
            'bold',
            '|',
            'link',
            'code',
            'strike',
            'underline',
            'highlight',
            '|',
            'bulletList',
            'orderedList',
            'br',
            'codeBlock',
            'blockquote',
            '|',
            'horizontalRule',
            'hardBreak',
            '|',
            'table',
            '|',
            'image',
            '|',
            'textAlign',
            '|',
            'rtl',
            '|',
            'history',
            '|',
            'editHtml',
        ];

        return [
            new Panel(__('Navigate to the next or previous story'), function () use ($request) {
                return $this->navigationLinks($request);
            }),
            ID::make()->sortable(),
            Text::make(__('Name'), 'name')->sortable()
                ->displayUsing(function ($name, $a, $b) {
                    $wrappedName = wordwrap($name, 75, "\n", true);
                    $htmlName = str_replace("\n", '<br>', $wrappedName);
                    return $htmlName;
                })
                ->asHtml()
                ->required(),
            Text::make('Info', function () use ($request) {
                $story = $this->resource;
                if (!empty($story->epic_id)) {
                    $epic = Epic::find($story->epic_id);
                    $project = Project::find($epic->project_id);
                } else {
                    $project = Project::find($story->project_id);
                }

                if ($project) {
                    $projectUrl = url('/resources/projects/' . $project->id);
                    $projectName = $project->name;
                } else {
                    $projectUrl = '';
                    $projectName = '';
                }
                $storyPriority = StoryPriority::getCase($this->priority);
                $storyStatus = $this->status;
                $storyType = $this->type;

                if (!$request->user()->hasRole(UserRole::Customer)) {
                    return '<a href="' . $projectUrl . '" target="_blank" style="color:grey; font-weight:bold;">' . "Project: " . $projectName . '</a>' . ' <br> ' . '<span style="color:' . ($this->priority == StoryPriority::Low->value ? 'green' : ($this->priority == StoryPriority::Medium->value ? 'orange' : 'red')) . '">' . "Priority: " . $storyPriority . '</span>' . ' <br> ' . "Status: " . $storyStatus . ' <br> ' . '<span style="color:blue">' . $storyType . '</span>';
                } else
                    //return the string without projecturl and priority
                    return "Status: " . $storyStatus . ' <br> ' . '<span style="color:blue">' . $storyType . '</span>';
            })->asHtml()
                ->onlyOnIndex()
                ->canSee(function ($request) {
                    return !$request->user()->hasRole(UserRole::Customer);
                }),
            Select::make(('Status'), 'status')->options($storyStatusOptions)->onlyOnForms()
                ->default('new')->canSee(function ($request) {
                    return !$request->user()->hasRole(UserRole::Customer);
                }),
            Text::make('Status', function () {
                $status = $this->status;
                $color = 'blue';
                return '<span style="color:' . $color . '; font-weight: bold;">' . $status . '</span>';
            })->asHtml()->canSee(function ($request) {
                return $request->user()->hasRole(UserRole::Customer);
            }),
            Select::make('Priority', 'priority')->options([
                StoryPriority::Low->value => 'Low',
                StoryPriority::Medium->value => 'Medium',
                StoryPriority::High->value => 'High',
            ])->onlyOnForms()
                ->default($this->priority ?? StoryPriority::High->value)
                ->canSee(function ($request) {
                    return !$request->user()->hasRole(UserRole::Customer);
                }),
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
                ->onlyOnDetail()
                ->canSee(function ($request) {
                    return !$request->user()->hasRole(UserRole::Customer);
                }),
            Select::make(__('Type'), 'type')->options(function () use ($request) {
                if ($request->user()->hasRole(UserRole::Customer)) {
                    return [
                        StoryType::Feature->value => 'Funzionalitá',
                        StoryType::Bug->value => 'Malfunzionamento',
                    ];
                } else {
                    return [
                        StoryType::Feature->value => 'Feature',
                        StoryType::Bug->value => 'Bug',
                    ];
                }
            })->onlyOnForms()
                ->default('Feature'),
            Text::make('Type', function () {
                $type = $this->type;
                $color = 'blue';
                return '<span style="color:' . $color . '; font-weight: bold;">' . $type . '</span>';
            })->asHtml()
                ->onlyOnDetail(),
            Text::make(__('Deadlines'), function () {
                $deadlines = $this->deadlines;
                foreach ($deadlines as $deadline) {
                    $dueDate = Carbon::parse($deadline->due_date)->format('Y-m-d');
                    $deadlineTitle = $deadline->title ?? '';
                    $customerName = isset($deadline->customer) ? $deadline->customer->name : '';
                    $deadlineName = $dueDate . '<br/>' . $deadlineTitle . '<br/>' . $customerName;
                    $deadlineLink = '<a href="' . url('/') . '/resources/deadlines/' . $deadline->id . '" style="color: green;">' . $deadlineName . '</a>';
                }
                return $deadlineLink ?? '';
            })->asHtml(),
            Tiptap::make(__('Description'), 'description')
                ->hideFromIndex()
                ->buttons($tiptapAllButtons)
                ->alwaysShow()
                ->canSee(function ($request) {
                    return !$request->user()->hasRole(UserRole::Customer);
                }),
            Tiptap::make(__('Customer Request'), 'customer_request')
                ->hideFromIndex()
                ->buttons(['heading', 'code', 'codeBlock', 'link', 'image', 'history', 'editHtml']),
            //TODO make it readonly when the package will be fixed( opened issue on github: https://github.com/manogi/nova-tiptap/issues/76 )

            BelongsTo::make('Developer', 'developer', 'App\Nova\User')
                ->default(function ($request) {
                    $epic = Epic::find($request->input('viaResourceId'));
                    return $epic ? $epic->user_id : null;
                })->canSee(function ($request) {
                    return !$request->user()->hasRole(UserRole::Customer);
                }),

            BelongsTo::make('Customer', 'creator', 'App\Nova\User')
                ->canSee(function ($request) {
                    return !$request->user()->hasRole(UserRole::Customer);
                }),
            BelongsTo::make('Tester', 'tester', 'App\Nova\User')
                ->canSee(function ($request) {
                    return !$request->user()->hasRole(UserRole::Customer);
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
                ->hideFromIndex()
                ->canSee(function ($request) {
                    return !$request->user()->hasRole(UserRole::Customer);
                }),
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
                ->hideFromIndex()
                ->canSee(function ($request) {
                    return !$request->user()->hasRole(UserRole::Customer);
                }),
            MorphToMany::make('Deadlines'),
            new Panel(__('Epic Description'), [
                MarkdownTui::make(__('Description'), 'epic.description')
                    ->hideFromIndex()
                    ->initialEditType(EditorType::MARKDOWN)
                    ->onlyOnDetail()
                    ->canSee(function ($request) {
                        return !$request->user()->hasRole(UserRole::Customer);
                    }),
            ]),
            Files::make('Documents', 'documents')
                ->hideFromIndex(),
            Images::make('Images', 'images')
                ->hideFromIndex(),

            $testDev !== null ? Text::make('DEV', function () use ($testDev) {
                $testDevLink = '<a style="color:green; font-weight:bold;" href="' . $testDev . '" target="_blank">' . $testDev . '</a>';
                return $testDevLink;
            })->asHtml()
                ->onlyOnDetail()
                ->canSee(function ($request) {
                    return !$request->user()->hasRole(UserRole::Customer);
                }) :
                Text::make('DEV', function () {
                    return '';
                })->asHtml()
                ->onlyOnDetail()
                ->canSee(function ($request) {
                    return !$request->user()->hasRole(UserRole::Customer);
                }),

            $testProd !== null ? Text::make('PROD', function () use ($testProd) {
                $testProdLink = '<a  style="color:green; font-weight:bold;" href="' . $testProd . '" target="_blank">' . $testProd . '</a>';
                return $testProdLink;
            })->asHtml()
                ->onlyOnDetail() :
                Text::make('PROD', function () {
                    return '';
                })->asHtml()
                ->onlyOnDetail()
                ->canSee(function ($request) {
                    return !$request->user()->hasRole(UserRole::Customer);
                }),

            //make the text fields for the url visible in the form
            Text::make('Test Dev', 'test_dev')
                ->rules('nullable', 'url:http,https')
                ->onlyOnForms()
                ->help('Url must start with http or https')
                ->canSee(function ($request) {
                    return !$request->user()->hasRole(UserRole::Customer);
                }),
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
            (new filters\UserFilter)->canSee(function ($request) {
                return !$request->user()->hasRole(UserRole::Customer);
            }),
            new filters\StoryStatusFilter,
            new filters\StoryTypeFilter,
            (new filters\StoryPriorityFilter)->canSee(function ($request) {
                return !$request->user()->hasRole(UserRole::Customer);
            }),
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
        if ($request->user()->hasRole(UserRole::Customer)) {
            return [];
        }
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
            array_push($actions, (new actions\ConvertStoryToEpic)
                ->confirmText('Click on the "Confirm" button to convert the selected stories to epics or "Cancel" to cancel.')
                ->confirmButtonText('Confirm')
                ->cancelButtonText('Cancel')
                ->showInline());
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

    public function navigationLinks(NovaRequest $request)
    {
        return [
            Text::make('Navigate')->onlyOnDetail()->asHtml()->displayUsing(function () {
                $epic = Epic::find($this->epic_id);
                if ($epic) {
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
                }
            })->canSee(function ($request) {
                return !$request->user()->hasRole(UserRole::Customer);
            }),
        ];
    }

    private function getOptions($loggedUser): array
    {
        $loggedUserIsDeveloperAssigned = false;
        $loggedUserIsTesterAssigned = false;
        $storyStatusOptions = [
            'new' => StoryStatus::New,
            'progress' => StoryStatus::Progress,
            'done' => StoryStatus::Done,
            'testing' => StoryStatus::Test,
            'rejected' => StoryStatus::Rejected,
        ];

        if ($this->resource->exists) {
            $loggedUserIsDeveloperAssigned = $this->resource->developer && $loggedUser->id == $this->resource->developer->id;
            $loggedUserIsTesterAssigned = $this->resource->tester && $loggedUser->id == $this->resource->tester->id;

            if ($loggedUserIsDeveloperAssigned && $loggedUserIsTesterAssigned) {
                $storyStatusOptions = [
                    'new' => StoryStatus::New,
                    'progress' => StoryStatus::Progress,
                    'done' => StoryStatus::Done,
                    'testing' => StoryStatus::Test,
                    'rejected' => StoryStatus::Rejected,
                ];
                return $storyStatusOptions;
            }
            if ($loggedUserIsDeveloperAssigned) {
                $storyStatusOptions = [
                    'new' => StoryStatus::New,
                    'progress' => StoryStatus::Progress,
                    'test' => StoryStatus::Test,
                ];
            }
            if ($loggedUserIsTesterAssigned) {
                $storyStatusOptions = [
                    'progress' => StoryStatus::Progress,
                    'done' => StoryStatus::Done,
                    'rejected' => StoryStatus::Rejected,
                ];
            }
        }

        return $storyStatusOptions;
    }
}
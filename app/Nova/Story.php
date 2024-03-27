<?php

namespace App\Nova;

use Carbon\Carbon;
use App\Models\Epic;
use App\Enums\UserRole;
use App\Models\Project;
use Laravel\Nova\Panel;
use App\Nova\Actions\EditStories;
use App\Enums\StoryType;
use Manogi\Tiptap\Tiptap;
use App\Enums\StoryStatus;
use Laravel\Nova\Fields\ID;
use App\Enums\StoryPriority;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Status;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Trix;
use Laravel\Nova\Fields\MorphToMany;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Nova\Http\Requests\NovaRequest;
use Ebess\AdvancedNovaMediaLibrary\Fields\Files;
use App\Nova\Actions\moveStoriesFromProjectToEpicAction;

class Story extends Resource
{

    public static function label()
    {
        return __('Stories');
    }

    /**
     * Get the plural label of the resource.
     *
     * @return string
     */
    public static function singularLabel()
    {
        return __('Story'); // Il nome plurale personalizzato
    }

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


    public function canSee($fieldName)
    {
        return function ($request) use ($fieldName) {
            return !in_array($fieldName, $this->hideFields);
        };
    }
    public $hideFields = [];
    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id', 'name', 'description'
    ];

    public $tiptapAllButtons = [
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
    public static function indexQuery(NovaRequest $request, $query)
    {
        if ($request->user()->hasRole(UserRole::Customer)) {
            return $query->where('creator_id', $request->user()->id)->where('status', '!=', StoryStatus::Done);
        }
    }

    public  function fieldsInIndex(NovaRequest $request)
    {
        $fields = [
            ID::make()->sortable(),
            $this->statusField($request),
            $this->typeField($request),
            $this->infoField($request),
            $this->titleField(),
            $this->createdAtField(),
            $this->updatedAtField(),
            $this->deadlineField($request),

        ];
        return array_map(function ($field) {
            return $field->onlyOnIndex();
        }, $fields);
    }

    public  function fieldsInDetails(NovaRequest $request)
    {
        $fields = [
            ID::make()->sortable(),
            $this->createdAtField(),
            $this->statusField($request),
            $this->creatorField(),
            $this->assignedToField(),
            $this->testedByField(),
            $this->infoField($request),
            $this->titleField(),
            $this->updatedAtField(),
            $this->deadlineField($request),
            $this->projectField(),
            Files::make('Documents', 'documents')
                ->rules('application/pdf', 'application/msword', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/json', 'applicationvnd.ms-powerpoint', 'text/plain', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                ->help('Only specific document types are allowed (PDF, DOC, DOCX, JSON, GeoJSON).'),
            $this->descriptionField(),
            $this->customerRequestField($request),
            MorphToMany::make('Deadlines')
                ->showCreateRelationButton()
        ];
        return array_map(function ($field) {
            return $field->onlyOnDetail();
        }, $fields);
    }
    public  function fieldsInEdit(NovaRequest $request)
    {
        $fields = [
            ID::make()->sortable(),
            $this->statusField($request),
            $this->creatorField(),
            $this->assignedToField(),
            $this->testedByField(),
            $this->typeField($request),
            $this->projectField(),
            Files::make('Documents', 'documents')
                ->rules('application/pdf', 'application/msword', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/json', 'applicationvnd.ms-powerpoint', 'text/plain', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                ->help('Only specific document types are allowed (PDF, DOC, DOCX, JSON, GeoJSON).'),
            $this->titleField(),
            $this->descriptionField(),
            $this->customerRequestField($request),
            $this->answerToTicketField(),
            MorphToMany::make('Deadlines')
                ->showCreateRelationButton()
        ];
        return array_map(function ($field) {
            return $field->onlyOnForms();
        }, $fields);
    }

    public function titleField($fieldName = 'name')
    {
        return Text::make(__('Title'), $fieldName)
            ->displayUsing(function ($name, $a, $b) {
                $wrappedName = wordwrap($name, 75, "\n", true);
                $htmlName = str_replace("\n", '<br>', $wrappedName);
                return $htmlName;
            })
            ->sortable()
            ->readonly(function ($request) {
                return $request->resourceId !== null;
            })
            ->required();
    }
    public function createdAtField($fieldName = 'created_at')
    {
        return DateTime::make(__('Created At'), $fieldName)
            ->sortable()
            ->displayUsing(function ($createdAt) {
                return Carbon::parse($createdAt)->format('d/m/Y, H:i');
            })
            ->canSee($this->canSee($fieldName));
    }
    public function updatedAtField($fieldName = 'updated_at')
    {
        return DateTime::make(__('Updated At'), $fieldName)
            ->sortable()
            ->displayUsing(function ($createdAt) {
                return Carbon::parse($createdAt)->format('d/m/Y, H:i');
            })
            ->canSee($this->canSee($fieldName));
    }
    public function typeField(NovaRequest $request, $fieldName = 'type')
    {
        $isEdit = $request->isCreateOrAttachRequest() || $request->isUpdateOrUpdateAttachedRequest();
        if ($isEdit) {
            return  Select::make(__('Type'), $fieldName)
                ->options(function () {
                    return [
                        StoryType::Feature->value =>  StoryType::Feature,
                        StoryType::Bug->value => StoryType::Bug,
                        StoryType::Helpdesk->value => StoryType::Helpdesk
                    ];
                })
                ->default(StoryType::Helpdesk->value)
                ->canSee($this->canSee($fieldName));
        } else {
            return Text::make(__('Type'), $fieldName, function () {
                $color = 'green';
                if ($this->type === StoryType::Bug->value) {
                    $color = 'red';
                } elseif ($this->type === StoryType::Feature->value) {
                    $color = 'blue'; // Assumendo che 'Feature' debba essere blu
                }

                return <<<HTML
    <span style="color:{$color}; font-weight: bold;">{$this->type}</span>
    HTML;
            })
                ->asHtml()
                ->canSee($this->canSee($fieldName));
        }
    }
    public function priorityField()
    {
        return  Select::make('Priority', 'priority')->options([
            StoryPriority::Low->value => 'Low',
            StoryPriority::Medium->value => 'Medium',
            StoryPriority::High->value => 'High',
        ])
            ->default($this->priority ?? StoryPriority::High->value)
            ->canSee(function ($request) {
                return !$request->user()->hasRole(UserRole::Customer);
            });
    }
    public function deadlineField(NovaRequest $request)
    {
        return Text::make(__('Deadlines'), 'deadlines', function () {
            $deadlines = $this->deadlines;
            foreach ($deadlines as $deadline) {
                $dueDate = Carbon::parse($deadline->due_date)->format('Y-m-d');
                $deadlineTitle = $deadline->title ?? '';
                $customerName = isset($deadline->customer) ? $deadline->customer->name : '';
                $deadlineName = $dueDate . '<br/>' . $deadlineTitle . '<br/>' . $customerName;
                $deadlineLink = '<a href="' . url('/') . '/resources/deadlines/' . $deadline->id . '" style="color: green;">' . $deadlineName . '</a>';
            }
            return $deadlineLink ?? '';
        })
            ->canSee($this->canSee('deadlines'))
            ->asHtml();
    }
    /**
     * Definisci un campo Status comune, con personalizzazioni per la vista.
     *
     * @return \Laravel\Nova\Fields\Field
     */
    public function statusField($request, $fieldName = 'status')
    {
        $isEdit = $request->isCreateOrAttachRequest() || $request->isUpdateOrUpdateAttachedRequest();
        if ($isEdit) {
            return   Select::make(__('Status'), $fieldName)
                ->options($this->getOptions())
                ->default(StoryStatus::New)
                ->readonly(function ($request) {
                    return $request->user()->hasRole(UserRole::Customer) && $this->resource->status !== StoryStatus::Released->value;
                });
        } else {
            return Status::make('Status', 'status')
                ->loadingWhen([
                    StoryStatus::Assigned->value, StoryStatus::Progress->value,
                    StoryStatus::Test->value, StoryStatus::Tested->value
                ])
                ->failedWhen([StoryStatus::New->value, StoryStatus::Rejected->value]);
        }
    }

    public function assignedToField()
    {
        return BelongsTo::make(__('assigned to'), 'developer', 'App\Nova\User')
            ->default(function ($request) {
                return auth()->user()->id;
            })
            ->canSee(function ($request) {
                return !$request->user()->hasRole(UserRole::Customer);
            })
            ->relatableQueryUsing(function (NovaRequest $request, Builder $query) {
                !$query->whereJsonDoesntContain('roles', UserRole::Customer);
            })
            ->nullable();
    }
    public function testedByField()
    {
        return BelongsTo::make(__('tested by'), 'tester', 'App\Nova\User')
            ->canSee(function ($request) {
                return !$request->user()->hasRole(UserRole::Customer);
            })
            ->nullable()
            ->relatableQueryUsing(function (NovaRequest $request, Builder $query) {
                !$query->whereJsonDoesntContain('roles', UserRole::Customer);
            })
            ->default(function ($request) {
                return auth()->user()->id;
            });
    }
    public function projectField($fieldName = 'project')
    {

        return BelongsTo::make(__('Project'), $fieldName)
            ->default(function ($request) {
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
            ->canSee($this->canSee($fieldName));
    }
    public function customerRequestField(NovaRequest $request, $fieldName = 'customer_request')
    {
        $customerRequestFieldEdit = Tiptap::make(__('Customer Request'), $fieldName)
            ->buttons(['heading', 'code', 'codeBlock', 'link', 'image', 'history', 'editHtml'])
            ->required()
            ->canSee(function ($request) use ($fieldName) {
                $creator = auth()->user();
                return  $creator->hasRole(UserRole::Customer);
            });

        if ($request->isCreateOrAttachRequest()) {
            return $customerRequestFieldEdit;
        } else if ($request->isResourceDetailRequest()) {
            return Text::make(__('Customer Request'), $fieldName)
                ->asHtml()
                ->canSee(function ($request) use ($fieldName) {
                    $creator = $this->resource->creator;
                    return $this->canSee($fieldName) &&  (isset($creator) && $creator->hasRole(UserRole::Customer));
                });
        } else {
            $creator = auth()->user();
            if (isset($creator) && $creator->hasRole(UserRole::Customer) && !isset($request->resourceId)) {
                return $customerRequestFieldEdit;
            } else {
                return Trix::make(__('Customer Request'), $fieldName)
                    ->readOnly()
                    ->canSee($this->canSee($fieldName));
            }
        }
    }
    public function answerToTicketField($fieldName = 'answer_to_ticket')
    {
        //TODO make it readonly when the package will be fixed( opened issue on github: https://github.com/manogi/nova-tiptap/issues/76 )
        return  Tiptap::make('Answer to ticket', $fieldName)
            ->canSee(
                function ($request) use ($fieldName) {
                    return  $this->canSee($fieldName) && $request->resourceId !== null;
                }
            )
            ->fillUsing(function ($request, $model, $attribute, $requestAttribute) {
                if (empty($request[$requestAttribute])) {
                    return;
                }
                $model->addResponse($request[$requestAttribute]);
            })
            ->buttons($this->tiptapAllButtons);
    }
    public function descriptionField()
    {
        return  Tiptap::make(__('Description'), 'description')
            ->hideFromIndex()
            ->buttons($this->tiptapAllButtons)
            ->canSee($this->canSee('description'))
            ->alwaysShow();
    }

    public function infoField(NovaRequest $request, $fieldName = 'info')
    {
        return Text::make(__('Info'), $fieldName, function () use ($request) {
            if ($request->user()->hasRole(UserRole::Customer)) {
                return $this->getCustomerInfo();
            } else {
                return $this->getNonCustomerInfo();
            }
        })
            ->canSee($this->canSee($fieldName))
            ->asHtml();
    }

    private function getCustomerInfo()
    {
        $statusColor = $this->getStatusColor($this->status);
        $storyType = $this->type;
        return <<<HTML
            Status: <span style="background-color:{$statusColor}; color: white; padding: 2px 4px;">{$this->status}</span> 
            <br> 
            <span style="color:blue">{$storyType}</span>
            HTML;
    }
    private function getNonCustomerInfo()
    {
        $projectLink = $this->getProjectLink();
        $creatorLink = $this->getCreatorLink();
        return "{$projectLink}{$creatorLink}";
    }

    private function getProjectLink()
    {
        $project = $this->resource->project ?? $this->resource->epic->project ?? null;
        if ($project) {
            $url = url("/resources/projects/{$project->id}");
            return <<<HTML
            <a 
                href="{$url}" 
                target="_blank" 
                style="color:orange; font-weight:bold;">
                Project: {$project->name}
            </a> <br>
            HTML;
        }
        return '';
    }
    private function getCreatorLink()
    {
        $creator = $this->resource->creator;
        if ($creator) {
            $url = url("/resources/users/{$creator->id}");
            return <<<HTML
            <a 
                href="{$url}" 
                target="_blank" 
                style="color:chocolate; font-weight:bold;">
                Creator: {$creator->name}
            </a> <br>
            HTML;
        }
        return '';
    }


    private function getStatusColor($status)
    {
        $mapping = config('orchestrator.story.status.color-mapping');
        return $mapping[$status] ?? 'black';
    }
    /**
     * Definisce un campo comune per il 'Creatore' con logiche specifiche.
     *
     * @return \Laravel\Nova\Fields\BelongsTo
     */
    public function creatorField()
    {
        $fieldName = 'creator';
        return BelongsTo::make('Creator', $fieldName, 'App\Nova\User')
            ->nullable()
            ->default(function ($request) {
                return auth()->user()->id;
            })
            ->readonly()
            ->canSee($this->canSee($fieldName));
    }

    /**
     * Get the fields displayed by the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [
            new Panel(__('Navigate to the next or previous story'), function () use ($request) {
                return $this->navigationLinks($request);
            }),
            ...$this->fieldsInIndex($request),
            ...$this->fieldsInDetails($request),
            ...$this->fieldsInEdit($request),

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
            return [
                (new actions\RespondToStoryRequest())
                    ->showInline()
                    ->sole()
                    ->confirmText('Click on the "Confirm" button to send the response or "Cancel" to cancel.')
                    ->confirmButtonText('Confirm')
                    ->cancelButtonText('Cancel')
                    ->canSee(
                        function ($request) {
                            return $this->status !== StoryStatus::Done->value && $this->status !== StoryStatus::Rejected->value;
                        }
                    )
            ];
        }
        $actions = [
            (new actions\RespondToStoryRequest())
                ->showInline()
                ->sole()
                ->confirmText('Click on the "Confirm" button to send the response or "Cancel" to cancel.')
                ->confirmButtonText('Confirm')
                ->cancelButtonText('Cancel')
                ->canSee(
                    function ($request) {
                        return $this->status !== StoryStatus::Done->value && $this->status !== StoryStatus::Rejected->value;
                    }
                ),
            (new EditStories)
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
            // array_push($actions, (new actions\ConvertStoryToEpic)
            //     ->confirmText('Click on the "Confirm" button to convert the selected stories to epics or "Cancel" to cancel.')
            //     ->confirmButtonText('Confirm')
            //     ->cancelButtonText('Cancel')      //TODO: delete when epic model will be deleted
            //     ->showInline());
            array_push($actions, (new actions\moveToBacklogAction)
                ->confirmText('Click on the "Confirm" button to move the selected stories to Backlog or "Cancel" to cancel.')
                ->confirmButtonText('Confirm')
                ->cancelButtonText('Cancel')
                ->showInline());
            // array_push($actions, (new MoveStoriesFromEpic)
            //     ->confirmText('Select the epic where you want to move the story. Click on "Confirm" to perform the action or "Cancel" to delete.')
            //     ->confirmButtonText('Confirm')       //TODO: delete when epic model will be deleted
            //     ->cancelButtonText('Cancel'));
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
                $deadline = $this->resource->deadlines->first();
                if ($deadline) {
                    $stories = $deadline->stories;
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

    public function getOptions(): array
    {
        $loggedUser = auth()->user();
        $loggedUserIsDeveloperAssigned = false;
        $loggedUserIsTesterAssigned = false;
        $statusOptions = [
            StoryStatus::New->value => StoryStatus::New,
            StoryStatus::Assigned->value => StoryStatus::Assigned,
            StoryStatus::Progress->value => StoryStatus::Progress,
            StoryStatus::Test->value => StoryStatus::Test,
            StoryStatus::Tested->value => StoryStatus::Tested,
            StoryStatus::Released->value => StoryStatus::Released,
            StoryStatus::Rejected->value => StoryStatus::Rejected,
            StoryStatus::Done->value => StoryStatus::Done,
        ];

        if ($this->resource->exists) {
            $loggedUserIsDeveloperAssigned = $this->resource->developer && $loggedUser->id == $this->resource->developer->id;
            $loggedUserIsTesterAssigned = $this->resource->tester && $loggedUser->id == $this->resource->tester->id;

            if ($loggedUserIsDeveloperAssigned && $loggedUserIsTesterAssigned) {
                return $statusOptions;
            }
            if ($loggedUserIsDeveloperAssigned) {
                unset($statusOptions[StoryStatus::New->value]);
                unset($statusOptions[StoryStatus::Tested->value]);
                unset($statusOptions[StoryStatus::Done->value]);
                unset($statusOptions[StoryStatus::Released->value]);
                return $statusOptions;
            }
            if ($loggedUserIsTesterAssigned) {
                unset($statusOptions[StoryStatus::New->value]);
                unset($statusOptions[StoryStatus::Test->value]);
                return $statusOptions;
            }
        }

        return $statusOptions;
    }


    function getStatusLabel($statusValue)
    {
        $statusOptions = [
            StoryStatus::New->value => StoryStatus::New,
            StoryStatus::Assigned->value => StoryStatus::Assigned,
            StoryStatus::Progress->value => StoryStatus::Progress,
            StoryStatus::Test->value => StoryStatus::Test,
            StoryStatus::Tested->value => StoryStatus::Tested,
            StoryStatus::Released->value => StoryStatus::Released,
            StoryStatus::Rejected->value => StoryStatus::Rejected,
            StoryStatus::Done->value => StoryStatus::Done,
        ];
        return $statusOptions[$statusValue] != null ? [$statusOptions[$statusValue]->value => $statusOptions[$statusValue]] : [];
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
            new filters\CreatorStoryFilter(),
            new filters\UserFilter(),
            new filters\StoryStatusFilter(),
            new filters\StoryTypeFilter(),
            new filters\CustomerStoryWithDeadlineFilter(),
        ];
    }
}

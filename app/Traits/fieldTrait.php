<?php

namespace App\Traits;

use App\Enums\DocumentationCategory;
use Carbon\Carbon;
use App\Models\Epic;
use App\Enums\UserRole;
use App\Models\Project;
use App\Nova\Tag as novaTag;
use App\Enums\StoryType;
use Manogi\Tiptap\Tiptap;
use App\Enums\StoryStatus;
use App\Enums\StoryPriority;
use App\Models\Documentation;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Status;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Trix;
use Laravel\Nova\Fields\Number;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Fields\Tag;

trait fieldTrait
{

    public $hideFields = [];
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

    public function canSee($fieldName)
    {
        return function ($request) use ($fieldName) {
            return !in_array($fieldName, $this->hideFields);
        };
    }

    public function titleField($fieldName = 'name')
    {
        return Text::make(__('Title'), $fieldName)
            ->displayUsing(function ($name, $a, $b) {
                return $this->trimText($name);
            })
            ->sortable()
            ->readonly(function ($request) {
                return $request->resourceId !== null;
            })
            ->required()
            ->asHtml();
    }

    public function trimText($text, $treshold = 50)
    {
        $wrappedText = wordwrap($text, $treshold, "\n", true);
        $htmlText = str_replace("\n", '<br>', $wrappedText);
        return $htmlText;
    }

    public function createdAtField($fieldName = 'created_at')
    {
        return DateTime::make(__('Created At'), $fieldName)
            ->sortable()
            ->displayUsing(function ($createdAt) {
                return Carbon::parse($createdAt)->format('d/m/Y');
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
                ->canSee(function ($request) {
                    return  !$request->user()->hasRole(UserRole::Customer);
                });
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

    public function historyLogField(NovaRequest $request,)
    {
        return  Text::make('History Log')
            ->onlyOnDetail()
            ->canSee(function ($request) {
                return !$request->user()->hasRole(UserRole::Customer);
            })
            ->asHtml();
    }

    public function ChildField(NovaRequest $request)
    {
        return Text::make(__('Childs'), 'childs', function () use ($request) {
            $childStories = $this->childStories;
            $childStoryLink = '';
            foreach ($childStories as $childStory) {
                $app = $this->getAppLink();
                $url = url("/resources/stories/{$childStory->id}");
                $story = <<<HTML
                <a 
                    href="{$url}" 
                    style="color: green;">
                    {$childStory->id}
                    </a>
                HTML;
                $childStoryLink .= $story . $app . $this->trimText($childStory->name, 30) . '<br>';
            }
            return $childStoryLink ?? '';
        })
            ->asHtml();
    }

    public function parentField(NovaRequest $request)
    {
        return Text::make(__('Parent'), 'parent', function () use ($request) {
            $parentStory = $this->parentStory;
            $parentStoryLink = '';
            if (is_null(($parentStory))) {
                return $parentStoryLink;
            }
            $app = $this->getAppLink();
            $url = url("/resources/stories/{$parentStory->id}");
            $story = <<<HTML
                <a 
                    href="{$url}" 
                    style="color: green;">
                    {$parentStory->id}
                    </a>
                HTML;
            $parentStoryLink .= $story . $app . $this->trimText($parentStory->name, 30) . '<br>';
            return $parentStoryLink ?? '';
        })
            ->asHtml();
    }

    public function relationshipField(NovaRequest $request)
    {
        return Text::make(__('Relationship'), 'relationship', function () use ($request) {
            // Controllo per la parent story
            if ($this->parentStory) {
                $parentStory = $this->parentStory;
                $parentStoryLink = '';
                if (is_null(($parentStory))) {
                    return $parentStoryLink;
                }
                $app = $this->getAppLink($parentStory->creator);
                $url = url("/resources/stories/{$parentStory->id}");
                $story = <<<HTML
                    <h3 style="color:yellow; font-weight: bold">PARENT:<h3/>
                    <a 
                        href="{$url}" 
                        style="color: green;">
                        {$parentStory->id}
                        </a>
                    HTML;
                $parentStoryLink .= $story . $app . $this->trimText($parentStory->name, 30) . '<br>';
                return $parentStoryLink ?? '';
            }

            // Controllo per le child stories
            if ($this->childStories->isNotEmpty()) {
                $childStories = $this->childStories;
                $childStoryLink = '';
                $storyHeader = <<<HTML
                <h3  style="color:yellow; font-weight: bold">CHILDS:<h3/>
                HTML;
                foreach ($childStories as $childStory) {
                    $app = $this->getAppLink($childStory->creator);
                    $url = url("/resources/stories/{$childStory->id}");
                    $story = <<<HTML
                    <a 
                        href="{$url}" 
                        style="color: green;">
                        {$childStory->id}
                        </a>
                    HTML;
                    $childStoryLink .= $story . $app . $this->trimText($childStory->name, 30) . '<br>';
                }
                return $storyHeader . $childStoryLink ?? '';
            }

            // Nessuna parent o child story
            return 'No relationship';
        })->canSee($this->canSee('relationship'))->asHtml();
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
                    StoryStatus::Assigned->value,
                    StoryStatus::Progress->value,
                    StoryStatus::Test->value,
                    StoryStatus::Tested->value
                ])
                ->failedWhen([
                    StoryStatus::New->value,
                    StoryStatus::Rejected->value,
                    storyStatus::Test->value,
                    StoryStatus::Waiting->value
                ]);
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

    public function tagsField($fieldLabel = 'Tags', $fieldName = 'tags')
    {
        return
            Tag::make($fieldLabel, $fieldName, novaTag::class)
            ->withPreview()
            ->canSee($this->canSee($fieldName));
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


    public function estimatedHoursFieldCanSee($fieldName)
    {
        return function ($request) use ($fieldName) {
            return ($request->user()->hasRole(UserRole::Developer) || $request->user()->hasRole(UserRole::Admin));
        };
    }

    public function estimatedHoursField(NovaRequest $request, $fieldName = 'estimated_hours')
    {
        return Number::make(__('Estimated Hours'), $fieldName)
            ->sortable()
            ->rules('nullable', 'numeric', 'min:0')
            ->help('Inserisci il tempo stimato per la risoluzione della storia in ore.')
            ->canSee($this->estimatedHoursFieldCanSee($fieldName));
    }

    private function getCustomerInfo()
    {

        $tagLinks = $this->getTagLinks(DocumentationCategory::Customer);
        return <<<HTML
            {$tagLinks}
            HTML;
    }

    private function getNonCustomerInfo()
    {
        $appLink = $this->getAppLink();
        $tagLinks = $this->getTagLinks();
        $creatorLink = $this->getCreatorLink();

        return "{$appLink}{$creatorLink}{$tagLinks}";
    }

    private function getAppLink($creator = null)
    {
        if (is_null($creator)) {
            $creator = $this->resource->creator;
        }
        $app = isset($creator) && isset($creator->apps) && count($creator->apps) > 0 ? $creator->apps[0] : null;

        if ($app) {
            $url = url("/resources/apps/{$app->id}");
            return <<<HTML
            <a 
                href="{$url}" 
                target="_blank" 
                style="color:red; font-weight:bold;">
                App: {$app->name}
            </a> <br>
            HTML;
        }
        return '';
    }

    private function getTagLinks(DocumentationCategory $category = DocumentationCategory::Internal)
    {
        $tags = $this->resource->tags;
        $tags = $tags->filter(function ($tag) use ($category) {
            if ($tag->taggable_type == "Documentation") {
                // Recupera la documentation associata
                $documentation = Documentation::find($tag->taggable_id);
                if ($documentation) {
                    // Se la categoria è Customer, filtra solo per Customer
                    if ($category == DocumentationCategory::Customer) {
                        return $documentation->category == DocumentationCategory::Customer;
                    }

                    // Se la categoria è Internal, mostra sia Internal che Customer
                    if ($category == DocumentationCategory::Internal) {
                        return in_array($documentation->category, [DocumentationCategory::Internal, DocumentationCategory::Customer]);
                    }
                }
            }
            return false;
        });
        $HTML = '';
        if ($tags) {
            foreach ($tags as $tag) {
                $url = $tag->getResourceUrlAttribute();
                $HTML .=    <<<HTML
            <a 
                href="$url"
                target="_blank" 
                style="color:orange; font-weight:bold;">
                {$tag->name}
            </a> <br>
            HTML;
            }
            return $HTML;
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

    public function getOptions(): array
    {
        $loggedUser = auth()->user();
        $allStatuses = collect(StoryStatus::cases())->mapWithKeys(fn($status) => [$status->value => $status]);

        if (!$this->resource->exists) {
            return $allStatuses->toArray();
        }

        $loggedUserIsDeveloperAssigned = $this->resource->developer && $loggedUser->id == $this->resource->developer->id;
        $loggedUserIsTesterAssigned = $this->resource->tester && $loggedUser->id == $this->resource->tester->id;

        if ($loggedUserIsDeveloperAssigned && ($loggedUserIsTesterAssigned || is_null($this->resource->tester))) {
            return $allStatuses->toArray();
        }

        if ($loggedUserIsDeveloperAssigned) {
            return $allStatuses->except([
                StoryStatus::New->value,
                StoryStatus::Tested->value,
                StoryStatus::Done->value,
                StoryStatus::Released->value
            ])->toArray();
        }

        if ($loggedUserIsTesterAssigned) {
            return $allStatuses->except([
                StoryStatus::New->value,
                StoryStatus::Test->value
            ])->toArray();
        }

        return $allStatuses->toArray();
    }


    function getStatusLabel($statusValue)
    {
        $statusOptions = [
            StoryStatus::New->value => StoryStatus::New,
            StoryStatus::Assigned->value => StoryStatus::Assigned,
            StoryStatus::Progress->value => StoryStatus::Progress,
            StoryStatus::Waiting->value => StoryStatus::Waiting,
            StoryStatus::Test->value => StoryStatus::Test,
            StoryStatus::Tested->value => StoryStatus::Tested,
            StoryStatus::Released->value => StoryStatus::Released,
            StoryStatus::Rejected->value => StoryStatus::Rejected,
            StoryStatus::Done->value => StoryStatus::Done,
        ];
        return $statusOptions[$statusValue] != null ? [$statusOptions[$statusValue]->value => $statusOptions[$statusValue]] : [];
    }
}

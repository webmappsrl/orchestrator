<?php

namespace App\Nova;

use App\Enums\UserRole;
use Laravel\Nova\Panel;
use App\Nova\Actions\EditStories;
use App\Enums\StoryStatus;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\MorphToMany;
use Laravel\Nova\Http\Requests\NovaRequest;
use Ebess\AdvancedNovaMediaLibrary\Fields\Files;
use App\Nova\Actions\moveStoriesFromProjectToEpicAction;
use App\Nova\Lenses\StoriesByQuarter;
use App\Traits\fieldTrait;
use Formfeed\Breadcrumbs\Breadcrumb;
use Formfeed\Breadcrumbs\Breadcrumbs;
use Illuminate\Support\Facades\Session;
use InteractionDesignFoundation\HtmlCard\HtmlCard;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\Stack;

class Story extends Resource
{
    use fieldTrait;

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
    public static $linkToParent = false;
    public static $resolveParentBreadcrumbs = false;
    public $holidayAlert = <<<HTML
    <div style="padding: 20px; border-radius: 8px; background-color: #f8f9fa; text-align: center; font-family: Arial, sans-serif; color: #333;">
        <h2 style="color: #dc3545; font-size: 24px; margin-bottom: 15px;">Avviso Importante</h2>
        <p style="font-size: 18px; line-height: 1.6;">
            Gentile Clientela,
        </p>
        <p style="font-size: 18px; line-height: 1.6;">
            Vi informiamo che il nostro servizio di ticketing sarà <strong>ridotto solo alle situazioni urgenti</strong> da 
            <strong>lunedì 12</strong> fino a <strong>venerdì 16</strong>. 
        </p>
        <p style="font-size: 18px; line-height: 1.6;">
            Il servizio riprenderà regolarmente da <strong>lunedì 19</strong>.
        </p>
        <p style="font-size: 18px; line-height: 1.6;">
            Vi ringraziamo per la vostra comprensione.
        </p>
    </div>
    HTML;

    public function indexBreadcrumb(NovaRequest $resourceClass, Breadcrumbs $breadcrumbs, Breadcrumb $indexBreadcrumb)
    {
        $previousUrl = url()->previous();
        $previousPath = parse_url($previousUrl, PHP_URL_PATH) . '?' . parse_url($previousUrl, PHP_URL_QUERY);
        if (strlen($previousPath) > 60) {
            Session::put('breadcrumb_path', $previousPath);
        }
        $bp = Session::get('breadcrumb_path');
        if (!is_null($bp)) {
            $indexBreadcrumb->path = $bp;
        }
        return $indexBreadcrumb;
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



    public $hideFields = [];
    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id',
        'name',
        'description'
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
            Stack::make('Title', [
                $this->typeField($request),
                $this->titleField(),
                $this->relationshipField($request),
            ]),
            Stack::make('Assigned/estimated hours', [
                $this->assignedToField(),
                $this->estimatedHoursField($request),
            ]),
            $this->infoField($request),
            $this->createdAtField(),
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
            $this->typeField($request),
            $this->statusField($request),
            $this->creatorField(),
            $this->assignedToField(),
            $this->testedByField(),
            $this->infoField($request),
            $this->estimatedHoursField($request),
            $this->updatedAtField(),
            $this->deadlineField($request),
            $this->tagsField(),
            $this->projectField(),
            Files::make('Documents', 'documents')
                ->singleMediaRules('mimetypes:application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/json,application/geo+json,text/plain,text/csv')
                ->help('Only specific document types are allowed (PDF, DOC, DOCX, JSON, GeoJSON, TXT, CSV).'),
            $this->descriptionField(),
            $this->titleField(),
            $this->customerRequestField($request),
            HasMany::make('Logs', 'views', StoryLog::class)->canSee(function ($request) {
                return !$request->user()->hasRole(UserRole::Customer);
            }),
            BelongsToMany::make('Child Stories', 'childStories', Story::class)
                ->nullable()
                ->searchable()
                ->canSee(function ($request) {
                    return empty($this->parent_id) &&  !$request->user()->hasRole(UserRole::Customer);
                })->filterable(),
            BelongsTo::make('Parent Story', 'parentStory', Story::class)
                ->nullable()
                ->searchable()
                ->canSee(function ($request) {
                    return !$request->user()->hasRole(UserRole::Customer);
                }),
            MorphToMany::make('Deadlines')
                ->showCreateRelationButton(),

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
            $this->tagsField(),
            $this->typeField($request),
            $this->projectField(),
            Files::make('Documents', 'documents')
                ->singleMediaRules('mimetypes:application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/json,application/geo+json,text/plain,text/csv')
                ->help('Only specific document types are allowed (PDF, DOC, DOCX, JSON, GeoJSON, TXT, CSV).'),
            $this->estimatedHoursField($request),
            $this->titleField(),
            $this->descriptionField(),
            $this->customerRequestField($request),
            $this->answerToTicketField(),
            BelongsTo::make('Parent Story', 'parentStory', CustomerStory::class)
                ->nullable()
                ->searchable()
                ->canSee(function ($request) {
                    return !$request->user()->hasRole(UserRole::Customer);
                }),
            MorphToMany::make('Deadlines')
                ->showCreateRelationButton()
        ];
        return array_map(function ($field) {
            return $field->onlyOnForms();
        }, $fields);
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
        return [(new HtmlCard())->width('full')->withMeta([
            'content' => $this->holidayAlert
        ])->center(true)];
    }



    /**
     * Get the lenses available for the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function lenses(NovaRequest $request)
    {
        return [
            new StoriesByQuarter('1'),
            new StoriesByQuarter('2'),
            new StoriesByQuarter('3'),
            new StoriesByQuarter('4'),
        ];
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
                ->cancelButtonText('Cancel')
                ->canSee(function () {
                    return $this->status !== StoryStatus::Progress->value;
                }),

            (new actions\StoryToDoneStatusAction)
                ->showInline()
                ->confirmText('Click on the "Confirm" button to save the status in Done or "Cancel" to cancel.')
                ->confirmButtonText('Confirm')
                ->cancelButtonText('Cancel')
                ->canSee(function () {
                    return $this->status !== StoryStatus::Done->value;
                }),

            (new actions\StoryToTestStatusAction)
                ->onlyInline()
                ->confirmText('Click on the "Confirm" button to save the status in Test or "Cancel" to cancel.')
                ->confirmButtonText('Confirm')
                ->cancelButtonText('Cancel')
                ->canSee(function () {
                    return $this->status !== StoryStatus::Test->value;
                }),

            (new actions\StoryToRejectedStatusAction)
                ->onlyInline()
                ->confirmText('Click on the "Confirm" button to save the status in Rejected or "Cancel" to cancel.')
                ->confirmButtonText('Confirm')
                ->cancelButtonText('Cancel')
                ->canSee(function () {
                    return $this->status !== StoryStatus::Rejected->value;
                }),

            (new actions\CreateDocumentationFromStory())
                ->confirmText('Click on the "Confirm" button to create a new documentation from the selected story or "Cancel" to cancel.')
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
        }

        return $actions;
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
            new Filters\TaggableTypeFilter(),
            new filters\StoryTypeFilter(),
        ];
    }
}

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
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\Stack;
use App\Nova\Deadline as novaDeadline;


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

    /**
     * Get the text for the create resource button.
     *
     * @return string|null
     */
    public static function createButtonLabel()
    {
        return __('Create Story');
    }

    /**
     * Get the text for the update resource button.
     *
     * @return string|null
     */
    public static function updateButtonLabel()
    {
        return __('Save Changes');
    }

    /**
     * Get the text for the attach resource button.
     *
     * @return string|null
     */
    public static function attachButtonLabel()
    {
        return __('Attach Story');
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

    public function fieldsInIndex(NovaRequest $request)
    {
        $fields = [
            ID::make()->sortable(),
            $this->statusField($request),
            Stack::make(__('Title'), [
                $this->typeField($request),
                $this->titleField(),
                $this->relationshipField($request),
            ]),
            Stack::make(__('Assigned/estimated hours'), [
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

    public function fieldsInDetails(NovaRequest $request)
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
            Files::make(__('Documents'), 'documents')
                ->singleMediaRules('mimetypes:application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/json,application/geo+json,text/plain,text/csv')
                ->help(__('Only specific document types are allowed (PDF, DOC, DOCX, JSON, GeoJSON, TXT, CSV).'))->onlyOnDetail(),
            $this->descriptionField(),
            $this->titleField(),
            $this->customerRequestField($request),
            HasMany::make(__('Logs'), 'views', StoryLog::class)->canSee(function ($request) {
                return !$request->user()->hasRole(UserRole::Customer);
            }),
            BelongsToMany::make(__('Child Stories'), 'childStories', Story::class)
                ->nullable()
                ->searchable()
                ->canSee(function ($request) {
                    return empty($this->parent_id) &&  !$request->user()->hasRole(UserRole::Customer);
                })->filterable(),
            BelongsTo::make(__('Parent Story'), 'parentStory', Story::class)
                ->nullable()
                ->searchable()
                ->canSee(function ($request) {
                    return !$request->user()->hasRole(UserRole::Customer);
                }),
            MorphToMany::make(__('Deadlines'), 'deadlines', novaDeadline::class)
                ->showCreateRelationButton(),

        ];
        return array_map(function ($field) {
            return $field->onlyOnDetail();
        }, $fields);
    }

    public function fieldsInEdit(NovaRequest $request)
    {
        $fields = [
            ID::make()->sortable(),
            $this->titleField(),
            $this->statusField($request),
            $this->creatorField(),
            $this->assignedToField(),
            $this->testedByField(),
            $this->tagsField(),
            $this->typeField($request),
            $this->descriptionField(),
            Files::make('Documents', 'documents')
                ->singleMediaRules('mimetypes:application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/json,application/geo+json,text/plain,text/csv')
                ->help(__('Only specific document types are allowed (PDF, DOC, DOCX, JSON, GeoJSON, TXT, CSV).')),
            $this->estimatedHoursField($request),
            $this->customerRequestField($request)
                ->help(
                    $request->resourceId
                        ? null
                        : __('Enter all the necessary information, such as the ID of the content you want to verify. You can insert images via `Add Image`. If you also want to send us a video, we recommend uploading it to a service like Google Drive, enabling link sharing, and inserting the link here. The more details you provide, the easier it will be for us to resolve the issue.')
                ),
            $this->answerToTicketField()
                ->help(
                    $request->resourceId
                        ? __('Enter all the necessary information, such as the ID of the content you want to verify. You can insert images via `Add Image`. If you also want to send us a video, we recommend uploading it to a service like Google Drive, enabling link sharing, and inserting the link here. The more details you provide, the easier it will be for us to resolve the issue.')
                        : null
                ),
            BelongsTo::make(__('Parent Story'), 'parentStory', Story::class)
                ->nullable()
                ->searchable()
                ->canSee(function ($request) {
                    return !$request->user()->hasRole(UserRole::Customer);
                })
                ->help(__('Here you can attach the ticket that has the same issue. If multiple tickets share the same issue, attach the main ticket to all related tickets. You can find the main ticket by searching for its title. It is important to note that when the main ticket status changes, the status of all related tickets will also be updated.')),
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
                    ->confirmText(__('Click on the "Confirm" button to send the response or "Cancel" to cancel.'))
                    ->confirmButtonText(__('Confirm'))
                    ->cancelButtonText(__('Cancel'))
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
                ->confirmText(__('Click on the "Confirm" button to send the response or "Cancel" to cancel.'))
                ->confirmButtonText(__('Confirm'))
                ->cancelButtonText(__('Cancel'))
                ->canSee(
                    function ($request) {
                        return $this->status !== StoryStatus::Done->value && $this->status !== StoryStatus::Rejected->value;
                    }
                ),
            (new EditStories)
                ->confirmText(__('Edit Status, User and Deadline for the selected stories. Click "Confirm" to save or "Cancel" to delete.'))
                ->confirmButtonText(__('Confirm'))
                ->cancelButtonText(__('Cancel')),

            (new actions\StoryToProgressStatusAction)
                ->onlyInline()
                ->confirmText(__('Click on the "Confirm" button to save the status in Progress or "Cancel" to cancel.'))
                ->confirmButtonText(__('Confirm'))
                ->cancelButtonText(__('Cancel'))
                ->canSee(function () {
                    return $this->status !== StoryStatus::Progress->value;
                }),

            (new actions\StoryToDoneStatusAction)
                ->showInline()
                ->confirmText(__('Click on the "Confirm" button to save the status in Done or "Cancel" to cancel.'))
                ->confirmButtonText(__('Confirm'))
                ->cancelButtonText(__('Cancel'))
                ->canSee(function () {
                    return $this->status !== StoryStatus::Done->value;
                }),

            (new actions\StoryToTestStatusAction)
                ->onlyInline()
                ->confirmText(__('Click on the "Confirm" button to save the status in Test or "Cancel" to cancel.'))
                ->confirmButtonText(__('Confirm'))
                ->cancelButtonText(__('Cancel'))
                ->canSee(function () {
                    return $this->status !== StoryStatus::Test->value;
                }),

            (new actions\StoryToRejectedStatusAction)
                ->onlyInline()
                ->confirmText(__('Click on the "Confirm" button to save the status in Rejected or "Cancel" to cancel.'))
                ->confirmButtonText(__('Confirm'))
                ->cancelButtonText(__('Cancel'))
                ->canSee(function () {
                    return $this->status !== StoryStatus::Rejected->value;
                }),
        ];
        if ($request->user()->hasRole(UserRole::Developer)) {
            array_push($actions, (new actions\CreateDocumentationFromStory())
                ->confirmText(__('Click on the "Confirm" button to create a new documentation from the selected story or "Cancel" to cancel.'))
                ->confirmButtonText(__('Confirm'))
                ->cancelButtonText(__('Cancel')));
        }
        if ($request->viaResource == 'projects') {
            array_push($actions, (new moveStoriesFromProjectToEpicAction)
                ->confirmText(__('Select the epic where you want to move the story. Click on "Confirm" to perform the action or "Cancel" to delete.'))
                ->confirmButtonText(__('Confirm'))
                ->cancelButtonText(__('Cancel')));
            array_push($actions, (new actions\createNewEpicFromStoriesAction)
                ->confirmText('Click on the "Confirm" button to create a new epic with selected stories or "Cancel" to cancel.')
                ->confirmButtonText(__('Confirm'))
                ->cancelButtonText(__('Cancel')));
        }

        if ($request->viaResource != 'projects') {

            array_push($actions, (new actions\moveToBacklogAction)
                ->confirmText(__('Click on the "Confirm" button to move the selected stories to Backlog or "Cancel" to cancel.'))
                ->confirmButtonText(__('Confirm'))
                ->cancelButtonText(__('Cancel'))
                ->showInline());
        }

        return $actions;
    }





    public function navigationLinks()
    {
        return [
            Text::make(__('Navigate'))->onlyOnDetail()->asHtml()->displayUsing(function () {
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

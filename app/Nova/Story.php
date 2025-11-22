<?php

namespace App\Nova;

use App\Enums\StoryStatus;
use App\Enums\UserRole;
use App\Nova\Actions\EditStories;
use App\Nova\Actions\moveStoriesFromProjectToEpicAction;
use App\Nova\Deadline as novaDeadline;
use App\Nova\Lenses\StoriesByQuarter;
use App\Nova\Metrics\StoryTime;
use App\Traits\fieldTrait;
use Ebess\AdvancedNovaMediaLibrary\Fields\Files;
use Formfeed\Breadcrumbs\Breadcrumb;
use Formfeed\Breadcrumbs\Breadcrumbs;
use Illuminate\Support\Facades\Session;
use InteractionDesignFoundation\HtmlCard\HtmlCard;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\MorphToMany;
use Laravel\Nova\Fields\Stack;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;
use Illuminate\Support\Str;

class Story extends Resource
{
    use fieldTrait;

    public static $trafficCop = false;

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

    public $holidayAlert = <<<'HTML'
    <div style="padding: 20px; border-radius: 8px; background-color: #f8f9fa; text-align: center; font-family: Arial, sans-serif; color: #333;">
        <h2 style="color: #dc3545; font-size: 24px; margin-bottom: 15px;">Avviso Importante</h2>
        <p style="font-size: 18px; line-height: 1.6;">
            Gentile Clientela,
        </p>
        <p style="font-size: 18px; line-height: 1.6;">
            Vi informiamo che durante i mesi di <strong>luglio e agosto</strong> il nostro servizio di ticketing 
            sarà attivo <strong>dal lunedì al giovedì</strong>.
        </p>
        <p style="font-size: 18px; line-height: 1.6;">
            Inoltre, comunichiamo la <strong>chiusura aziendale per la settimana di ferragosto</strong>
            dal <strong>10 al 15 agosto</strong>.
             Il servizio riprenderà  da <strong>lunedì 17</strong>.
        </p>
        <p style="font-size: 18px; line-height: 1.6;">
            Vi ringraziamo per la vostra comprensione.
        </p>
    </div>
    HTML;

    public function indexBreadcrumb(NovaRequest $resourceClass, Breadcrumbs $breadcrumbs, Breadcrumb $indexBreadcrumb)
    {
        $previousUrl = url()->previous();
        $previousPath = parse_url($previousUrl, PHP_URL_PATH).'?'.parse_url($previousUrl, PHP_URL_QUERY);
        if (strlen($previousPath) > 60) {
            Session::put('breadcrumb_path', $previousPath);
        }
        $bp = Session::get('breadcrumb_path');
        if (! is_null($bp)) {
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
        'description',
    ];

    public static function indexQuery(NovaRequest $request, $query)
    {
        if ($request->user() != null && $request->user()->hasRole(UserRole::Customer)) {
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
            Stack::make(__('ASSIGNED/HOURS'), [
                $this->assignedToField(),
                $this->estimatedHoursField($request),
                $this->effectiveHoursField($request),
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
            $this->typeField($request),
            $this->statusField($request),
            $this->waitingReasonField(),
            $this->problemReasonField(),
            $this->creatorField(),
            $this->assignedToField(),
            $this->testedByField(),
            $this->infoField($request),
            $this->estimatedHoursField($request),
            $this->deadlineField($request),
            $this->tagsField(),
            Files::make(__('Documents'), 'documents')
                ->singleMediaRules(static::getDocumentsMimetypesRule())
                ->help(static::getDocumentsHelpText())
                ->onlyOnDetail(),
            $this->descriptionField(),
            $this->titleField(),
            $this->customerRequestField($request),
            
            // Ticket history and activities panel
            new Panel(__('Ticket history and activities'), [
                DateTime::make(__('Created At'), 'created_at')
                    ->displayUsing(function ($date) {
                        return $date ? $date->format('d/m/Y H:i') : '-';
                    })
                    ->hideWhenCreating()
                    ->hideWhenUpdating(),
                
                DateTime::make(__('Updated At'), 'updated_at')
                    ->displayUsing(function ($date) {
                        return $date ? $date->format('d/m/Y H:i') : '-';
                    })
                    ->hideWhenCreating()
                    ->hideWhenUpdating(),
                
                DateTime::make(__('Released At'), 'released_at')
                    ->displayUsing(function ($date) {
                        return $date ? $date->format('d/m/Y H:i') : '-';
                    })
                    ->hideWhenCreating()
                    ->hideWhenUpdating(),
                
                DateTime::make(__('Done At'), 'done_at')
                    ->displayUsing(function ($date) {
                        return $date ? $date->format('d/m/Y H:i') : '-';
                    })
                    ->hideWhenCreating()
                    ->hideWhenUpdating(),
                
                Text::make(__('Story Log'), function () {
                    $logs = $this->storyLogs()->with('user')->orderBy('viewed_at', 'desc')->get();
                    
                    if ($logs->isEmpty()) {
                        return __('No log entries found.');
                    }
                    
                    $logEntries = [];
                    foreach ($logs as $log) {
                        $userName = $log->user ? $log->user->name : __('Unknown User');
                        $date = $log->viewed_at ? $log->viewed_at->format('d/m/Y H:i') : '-';
                        
                        $changes = $log->changes ?? [];
                        $statusChange = null;
                        $otherChanges = [];
                        
                        foreach ($changes as $key => $value) {
                            if ($key === 'watch' || $key === 'updated_at') {
                                continue; // Skip watch and updated_at entries
                            }
                            
                            if ($key === 'status') {
                                $statusValue = is_array($value) ? json_encode($value) : (string)$value;
                                $statusChange = 'status: <strong>' . $statusValue . '</strong>';
                            } elseif ($key === 'description') {
                                $otherChanges[] = __('Description') . ': ' . __('changed');
                            } elseif (is_array($value)) {
                                $otherChanges[] = $key . ': ' . json_encode($value);
                            } else {
                                $displayValue = Str::limit((string)$value, 100, '...');
                                $otherChanges[] = $key . ': ' . $displayValue;
                            }
                        }
                        
                        $changeParts = [];
                        if ($statusChange) {
                            $changeParts[] = $statusChange;
                        }
                        if (!empty($otherChanges)) {
                            $changeParts = array_merge($changeParts, $otherChanges);
                        }
                        
                        if (!empty($changeParts)) {
                            $logEntries[] = '[' . $date . '] ' . $userName . ' / ' . implode(' / ', $changeParts);
                        }
                    }
                    
                    return implode('<br>', $logEntries);
                })
                    ->asHtml()
                    ->hideWhenCreating()
                    ->hideWhenUpdating()
                    ->canSee(function ($request) {
                        if ($request->user() == null) {
                            return false;
                        }
                        return ! $request->user()->hasRole(UserRole::Customer);
                    }),
                
                $this->userActivityField()->canSee(function ($request) {
                    if ($request->user() == null) {
                        return false;
                    }
                    return ! $request->user()->hasRole(UserRole::Customer);
                }),
            ]),
            
            BelongsToMany::make(__('Child Stories'), 'childStories', Story::class)
                ->nullable()
                ->searchable()
                ->canSee(function ($request) {
                    if ($request->user() == null) {
                        return false;
                    }
                    return empty($this->parent_id) && ! $request->user()->hasRole(UserRole::Customer);
                })->filterable(),
            BelongsTo::make(__('Parent Story'), 'parentStory', Story::class)
                ->nullable()
                ->searchable()
                ->canSee(function ($request) {
                    if ($request->user() == null) {
                        return false;
                    }
                    return ! $request->user()->hasRole(UserRole::Customer);
                }),
            MorphToMany::make(__('Deadlines'), 'deadlines', novaDeadline::class)
                ->showCreateRelationButton(),

        ];

        return array_map(function ($field) {
            // Panel doesn't support onlyOnDetail(), so skip it
            if ($field instanceof Panel) {
                return $field;
            }
            return $field->onlyOnDetail();
        }, $fields);
    }

    public function fieldsInEdit(NovaRequest $request)
    {
        $fields = [
            ID::make()->sortable(),
            $this->titleField(),
            $this->statusField($request),
            $this->waitingReasonField(),
            $this->problemReasonField(),
            $this->creatorField(),
            $this->assignedToField(),
            $this->testedByField(),
            $this->tagsField(),
            $this->typeField($request),
            $this->descriptionField(),
            Files::make('Documents', 'documents')
                ->singleMediaRules(static::getDocumentsMimetypesRule())
                ->help(static::getDocumentsHelpText()),
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
                    if ($request->user() == null) {
                        return false;
                    }
                    return ! $request->user()->hasRole(UserRole::Customer);
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
     * @return array
     */
    public function cards(NovaRequest $request)
    {
        return [
            (new StoryTime)->refreshWhenFiltersChange()->canSee(function ($request) {
                if ($request->user() == null) {
                    return false;
                }
                return ! $request->user()->hasRole(UserRole::Customer);
            }),
            (new StoryTime)->onlyOnDetail()->canSee(function ($request) {
                if ($request->user() == null) {
                    return false;
                }
                return ! $request->user()->hasRole(UserRole::Customer);
            }),
            (new HtmlCard())->width('full')->withMeta([
                'content' => $this->holidayAlert,
            ])->center(true)->canSee(function ($request) {
                return false;
            }),
        ];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @return array
     */
    public function lenses(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @return array
     */
    public function actions(NovaRequest $request)
    {
        if ($request->user() != null && $request->user()->hasRole(UserRole::Customer)) {
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
                    ),
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

            (new actions\StoryToTodoStatusAction)
                ->onlyInline()
                ->confirmText('Click on the "Confirm" button to save the status in Todo or "Cancel" to cancel.')
                ->confirmButtonText('Confirm')
                ->cancelButtonText('Cancel'),

            (new actions\ConvertStoryToTagAction)
                ->onlyOnDetail()
                ->confirmText(__('Do you want to convert the selected ticket to tag?'))
                ->confirmButtonText(__('Confirm'))
                ->cancelButtonText(__('Cancel')),
        ];
        if ($request->user() != null && ($request->user()->hasRole(UserRole::Developer) || $request->user()->hasRole(UserRole::Admin))) {
            array_push($actions, (new actions\CreateDocumentationFromStory())
                ->confirmText(__('Click on the "Confirm" button to create a new documentation from the selected story or "Cancel" to cancel.'))
                ->confirmButtonText(__('Confirm'))
                ->cancelButtonText(__('Cancel')));
            
            array_push($actions, (new actions\ChangeStoryCreator())
                ->confirmText(__('Click on the "Confirm" button to change the creator of the selected story or "Cancel" to cancel.'))
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
                        $previousLink = '<a href="/resources/stories/'.$previousStory->id.'" style="font-size: 30px;">⬅️</a>';
                    }

                    if ($nextStory != null) {
                        $nextLink = '<a href="/resources/stories/'.$nextStory->id.'" style="font-size: 30px;">➡️</a>';
                    }

                    return $previousLink.'&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'.$nextLink;
                }
            })->canSee(function ($request) {
                if ($request->user() == null) {
                    return false;
                }
                return ! $request->user()->hasRole(UserRole::Customer);
            }),
        ];
    }

    /**
     * Get the filters available for the resource.
     *
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
            new filters\StoryWithoutTagsFilter(),
            new filters\StoryWithMultipleTagsFilter(),
        ];
    }

    /**
     * Get all allowed file extensions from configuration.
     *
     * @return array
     */
    public static function getAllowedFileExtensions()
    {
        $config = config('orchestrator.story_allowed_file_types', []);
        return array_merge(
            $config['documents'] ?? [],
            $config['images'] ?? [],
            $config['audio'] ?? []
        );
    }

    /**
     * Get all allowed MIME types from configuration.
     *
     * @return array
     */
    public static function getAllowedMimeTypes()
    {
        $config = config('orchestrator.story_allowed_mime_types', []);
        return array_merge(
            $config['documents'] ?? [],
            $config['images'] ?? [],
            $config['audio'] ?? []
        );
    }

    /**
     * Get the effective maximum file size considering PHP ini limits.
     * Returns the minimum between configured size and PHP ini limits.
     *
     * @return int Size in bytes
     */
    public static function getEffectiveMaxFileSize()
    {
        $configuredSize = config('orchestrator.story_max_file_size', 10 * 1024 * 1024);
        
        // Get PHP ini limits
        $uploadMaxFilesize = static::parsePhpIniSize(ini_get('upload_max_filesize'));
        $postMaxSize = static::parsePhpIniSize(ini_get('post_max_size'));
        
        // Use the minimum of all three values
        return min($configuredSize, $uploadMaxFilesize, $postMaxSize);
    }

    /**
     * Parse PHP ini size string (e.g., "10M", "1024K") to bytes.
     *
     * @param string $size
     * @return int Size in bytes
     */
    private static function parsePhpIniSize($size)
    {
        if (empty($size)) {
            return PHP_INT_MAX; // No limit
        }

        $size = trim($size);
        $last = strtolower($size[strlen($size) - 1]);
        $value = (int) $size;

        switch ($last) {
            case 'g':
                $value *= 1024;
                // no break
            case 'm':
                $value *= 1024;
                // no break
            case 'k':
                $value *= 1024;
        }

        return $value;
    }

    /**
     * Generate mimetypes validation rule string.
     *
     * @return string
     */
    public static function getDocumentsMimetypesRule()
    {
        $mimeTypes = static::getAllowedMimeTypes();
        $maxFileSize = static::getEffectiveMaxFileSize();
        $maxFileSizeKB = round($maxFileSize / 1024);
        
        return 'mimetypes:' . implode(',', $mimeTypes) . '|max:' . $maxFileSizeKB;
    }

    /**
     * Generate dynamic help text for file uploads.
     *
     * @return string
     */
    public static function getDocumentsHelpText()
    {
        $config = config('orchestrator.story_allowed_file_types', []);
        $maxFileSize = static::getEffectiveMaxFileSize();
        
        $parts = [];
        
        if (!empty($config['documents'])) {
            $docExtensions = array_map('strtoupper', $config['documents']);
            $parts[] = '**Documenti:** ' . implode(', ', $docExtensions);
        }
        
        if (!empty($config['images'])) {
            $imgExtensions = array_map('strtoupper', $config['images']);
            $parts[] = '**Immagini:** ' . implode(', ', $imgExtensions);
        }
        
        if (!empty($config['audio'])) {
            $audioExtensions = array_map('strtoupper', $config['audio']);
            $parts[] = '**Audio:** ' . implode(', ', $audioExtensions) . ' (per verbalizzazione)';
        }
        
        // Convert bytes to human-readable format
        $maxSizeMB = round($maxFileSize / (1024 * 1024), 1);
        $sizeText = $maxSizeMB >= 1 ? $maxSizeMB . ' MB' : round($maxFileSize / 1024, 1) . ' KB';
        
        $helpText = __('Sono consentiti solo i seguenti tipi di file:') . "\n\n" . implode("\n", $parts);
        $helpText .= "\n\n**Dimensione massima:** " . $sizeText;
        
        return $helpText;
    }
}

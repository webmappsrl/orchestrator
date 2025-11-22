<?php

namespace App\Nova;

use App\Enums\StoryStatus;
use App\Enums\StoryType;
use App\Enums\UserRole;
use App\Nova\User;
use App\Nova\Organization;
use App\Nova\StoryLog;
use App\Traits\fieldTrait;
use Ebess\AdvancedNovaMediaLibrary\Fields\Files;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\MorphToMany;
use Laravel\Nova\Fields\Stack;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;
use Laravel\Nova\Resource;
use App\Nova\Deadline as novaDeadline;

class TicketReport extends Resource
{
    use fieldTrait;

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
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id',
        'name',
        'description',
    ];

    /**
     * Get the displayable label of the resource.
     *
     * @return string
     */
    public static function label()
    {
        return __('Tickets Report');
    }

    /**
     * Get the displayable singular label of the resource.
     *
     * @return string
     */
    public static function singularLabel()
    {
        return __('Ticket Report');
    }

    /**
     * Build an "index" query for the given resource.
     * Shows only tickets with status Released or Done
     * Supports filtering by date range via query parameters
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function indexQuery(NovaRequest $request, $query)
    {
        $query = $query->whereIn('status', [
            StoryStatus::Released->value,
            StoryStatus::Done->value,
        ])
        ->where('type', '!=', StoryType::Scrum->value);

        // Support for date range filtering via query parameters
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        if ($startDate || $endDate) {
            $query->where(function ($q) use ($startDate, $endDate) {
                // Filter by start date: ticket must have at least one date >= start_date
                if ($startDate) {
                    $q->where(function ($subQ) use ($startDate) {
                        $subQ->whereDate('created_at', '>=', $startDate)
                            ->orWhere(function ($dateQ) use ($startDate) {
                                $dateQ->whereNotNull('released_at')
                                    ->whereDate('released_at', '>=', $startDate);
                            })
                            ->orWhere(function ($dateQ) use ($startDate) {
                                $dateQ->whereNotNull('done_at')
                                    ->whereDate('done_at', '>=', $startDate);
                            });
                    });
                }
                // Filter by end date: ticket must have at least one date <= end_date
                if ($endDate) {
                    $q->where(function ($subQ) use ($endDate) {
                        $subQ->whereDate('created_at', '<=', $endDate)
                            ->orWhere(function ($dateQ) use ($endDate) {
                                $dateQ->whereNotNull('released_at')
                                    ->whereDate('released_at', '<=', $endDate);
                            })
                            ->orWhere(function ($dateQ) use ($endDate) {
                                $dateQ->whereNotNull('done_at')
                                    ->whereDate('done_at', '<=', $endDate);
                            });
                    });
                }
            });
        }

        // Use select to ensure all columns needed for ordering are included
        return $query->select('stories.*')->orderBy('created_at', 'asc');
    }

    /**
     * Get the fields displayed by the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        // Campi per la index view
        return [
            ID::make()->sortable(),

            Stack::make(__('Title'), [
                $this->typeField($request),
                $this->titleField(),
                $this->relationshipField($request),
            ]),

            $this->createdAtField()->sortable(),

            $this->statusField($request)->sortable(),

            \Laravel\Nova\Fields\Date::make(__('Released At'), 'released_at')
                ->sortable()
                ->displayUsing(function ($date) {
                    return $date ? $date->format('d/m/Y') : '-';
                }),

            \Laravel\Nova\Fields\Date::make(__('Done At'), 'done_at')
                ->sortable()
                ->displayUsing(function ($date) {
                    return $date ? $date->format('d/m/Y') : '-';
                }),

            BelongsTo::make(__('Creator'), 'creator', User::class)
                ->sortable()
                ->searchable(),

            Text::make(__('Organization'), function () {
                if (!$this->creator || !$this->creator->organizations) {
                    return '-';
                }
                $organizations = $this->creator->organizations;
                if ($organizations->isEmpty()) {
                    return '-';
                }
                return $organizations->pluck('name')->join(', ');
            }),
        ];
    }

    /**
     * Get the fields for the detail view (same as CustomerStory/Story).
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function fieldsForDetail(NovaRequest $request)
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
                ->singleMediaRules(Story::getDocumentsMimetypesRule())
                ->help(Story::getDocumentsHelpText())
                ->onlyOnDetail(),
            $this->descriptionField(),
            $this->titleField(),
            $this->customerRequestField($request),
            
            // Ticket history and activities panel
            Panel::make(__('Ticket history and activities'), [
                DateTime::make(__('Created At'), 'created_at')
                    ->displayUsing(function ($date) {
                        return $date ? $date->format('d/m/Y H:i') : '-';
                    }),
                
                DateTime::make(__('Updated At'), 'updated_at')
                    ->displayUsing(function ($date) {
                        return $date ? $date->format('d/m/Y H:i') : '-';
                    }),
                
                DateTime::make(__('Released At'), 'released_at')
                    ->displayUsing(function ($date) {
                        return $date ? $date->format('d/m/Y H:i') : '-';
                    }),
                
                DateTime::make(__('Done At'), 'done_at')
                    ->displayUsing(function ($date) {
                        return $date ? $date->format('d/m/Y H:i') : '-';
                    }),
                
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
                    ->asHtml(),
                
                $this->userActivityField(),
            ])->collapsible(),
            
            BelongsToMany::make(__('Child Stories'), 'childStories', Story::class)
                ->nullable()
                ->searchable()
                ->canSee(function ($request) {
                    if ($request->user() == null) {
                        return false;
                    }
                    return empty($this->parent_id);
                })
                ->filterable(),
            BelongsTo::make(__('Parent Story'), 'parentStory', Story::class)
                ->nullable()
                ->searchable()
                ->canSee(function ($request) {
                    if ($request->user() == null) {
                        return false;
                    }
                    return true; // TicketReport è solo per admin/developer
                }),
            MorphToMany::make(__('Deadlines'), 'deadlines', novaDeadline::class)
                ->showCreateRelationButton(),
        ];

        return $fields;
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
            new Filters\TicketReportStatusFilter(),
            new Filters\CreatorStoryFilter(),
            new Filters\TicketReportOrganizationFilter(),
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
        return [
            new Actions\ExportTicketReportToPdf(),
        ];
    }

    /**
     * Determine if this resource is available for navigation.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    public static function availableForNavigation(Request $request)
    {
        if ($request->user() == null) {
            return false;
        }

        return $request->user()->hasRole(UserRole::Admin) || $request->user()->hasRole(UserRole::Developer);
    }

    /**
     * Determine if this resource is available for creation.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    public static function authorizedToCreate(Request $request)
    {
        return false;
    }

    /**
     * Determine if the given resource is authorized to be updated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    public function authorizedToUpdate(Request $request)
    {
        return false;
    }

    /**
     * Determine if the given resource is authorized to be deleted.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    public function authorizedToDelete(Request $request)
    {
        return false;
    }

    /**
     * Get the URI key for the resource.
     *
     * @return string
     */
    public static function uriKey()
    {
        return 'ticket-reports';
    }
}

<?php

namespace App\Nova;

use App\Enums\StoryStatus;
use App\Enums\UserRole;
use App\Nova\User;
use App\Traits\fieldTrait;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Stack;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Resource;

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
        ]);

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
            new Filters\CreatorStoryFilter(),
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

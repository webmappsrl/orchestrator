<?php

namespace App\Nova;

use App\Enums\OwnerType;
use App\Enums\ReportType;
use App\Enums\UserRole;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Resource;

class CustomerActivityReport extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\ActivityReport>
     */
    public static $model = \App\Models\ActivityReport::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'id';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id',
    ];

    /**
     * Get the displayable label of the resource.
     *
     * @return string
     */
    public static function label()
    {
        return __('Report');
    }

    /**
     * Get the displayable singular label of the resource.
     *
     * @return string
     */
    public static function singularLabel()
    {
        return __('Activity Report');
    }

    /**
     * Build an "index" query for the given resource.
     * Shows only reports for the current customer with PDF available.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function indexQuery(NovaRequest $request, $query)
    {
        $user = $request->user();
        
        if (!$user || !$user->hasRole(UserRole::Customer)) {
            return $query->whereRaw('1 = 0'); // Return empty result
        }

        // Filter by customer (owner_type = customer and customer_id = current user)
        // And only show reports with PDF available
        return $query->where('owner_type', OwnerType::Customer->value)
            ->where('customer_id', $user->id)
            ->whereNotNull('pdf_url')
            ->orderBy('created_at', 'desc');
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
        return $request->user()->hasRole(UserRole::Customer);
    }

    /**
     * Determine if the user can create new resources.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    public static function authorizedToCreate(Request $request)
    {
        return false; // Customers cannot create reports, only view them
    }

    /**
     * Determine if the user can update the given resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    public function authorizedToUpdate(Request $request)
    {
        return false; // Customers cannot update reports
    }

    /**
     * Determine if the user can delete the given resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    public function authorizedToDelete(Request $request)
    {
        return false; // Customers cannot delete reports
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

            Select::make(__('Report Type'), 'report_type')
                ->options([
                    ReportType::Annual->value => __('Annual'),
                    ReportType::Monthly->value => __('Monthly'),
                ])
                ->displayUsingLabels()
                ->sortable(),

            Number::make(__('Year'), 'year')
                ->sortable(),

            Number::make(__('Month'), 'month')
                ->hideFromIndex()
                ->displayUsing(function ($month) {
                    if (!$month) {
                        return '-';
                    }
                    return \Carbon\Carbon::create(null, $month)->format('F');
                }),

            Text::make(__('Period'), function () {
                return $this->period ?? '-';
            }),

            Text::make(__('PDF URL'), 'pdf_url')
                ->displayUsing(function ($url) {
                    return $url ? '<a href="' . $url . '" target="_blank" class="link-default">' . __('Download PDF') . '</a>' : '-';
                })
                ->asHtml(),
        ];
    }

    /**
     * Get the fields for the detail view.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function fieldsForDetail(NovaRequest $request)
    {
        return array_merge($this->fields($request), [
            BelongsToMany::make(__('Stories'), 'stories', Story::class)
                ->searchable()
                ->sortable(),
        ]);
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
        return []; // Customers cannot generate PDFs, only download existing ones
    }
}


<?php

namespace App\Nova;

use App\Enums\OwnerType;
use App\Enums\ReportType;
use App\Enums\UserRole;
use App\Nova\Actions\GenerateActivityReportPdf;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Resource;
use Khalin\Nova4SearchableBelongsToFilter\NovaSearchableBelongsToFilter;

class ActivityReport extends Resource
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
        return __('Activity Reports');
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
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function indexQuery(NovaRequest $request, $query)
    {
        return $query->with(['customer', 'organization']);
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
        return $request->user()->hasRole(UserRole::Admin);
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

            Select::make(__('Owner Type'), 'owner_type')
                ->options([
                    OwnerType::Customer->value => __('Customer'),
                    OwnerType::Organization->value => __('Organization'),
                ])
                ->displayUsingLabels()
                ->rules('required')
                ->sortable()
                ->hideFromIndex(),

            BelongsTo::make(__('Customer'), 'customer', User::class)
                ->nullable()
                ->searchable()
                ->rules('required_if:owner_type,' . OwnerType::Customer->value)
                ->hideFromIndex()
                ->dependsOn(['owner_type'], function ($field, $request, $formData) {
                    if (isset($formData['owner_type']) && $formData['owner_type'] === OwnerType::Customer->value) {
                        $field->show();
                    } else {
                        $field->hide();
                    }
                }),

            BelongsTo::make(__('Organization'), 'organization', Organization::class)
                ->nullable()
                ->searchable()
                ->rules('required_if:owner_type,' . OwnerType::Organization->value)
                ->hideFromIndex()
                ->dependsOn(['owner_type'], function ($field, $request, $formData) {
                    if (isset($formData['owner_type']) && $formData['owner_type'] === OwnerType::Organization->value) {
                        $field->show();
                    } else {
                        $field->hide();
                    }
                }),

            Text::make(__('Owner'), 'owner_info')
                ->onlyOnIndex()
                ->displayUsing(function () {
                    $ownerName = $this->owner_name ?? '-';
                    $ownerType = $this->owner_type ? __(ucfirst($this->owner_type->value)) : '-';
                    
                    // Get language from customer or organization
                    $language = 'it'; // default
                    $languageLabel = 'IT';
                    if ($this->owner_type === OwnerType::Customer && $this->customer_id) {
                        $customer = $this->customer;
                        if ($customer && $customer->activity_report_language) {
                            $language = $customer->activity_report_language;
                            $languageLabel = strtoupper($customer->activity_report_language);
                        }
                    } elseif ($this->owner_type === OwnerType::Organization && $this->organization_id) {
                        $organization = $this->organization;
                        if ($organization && $organization->activity_report_language) {
                            $language = $organization->activity_report_language;
                            $languageLabel = strtoupper($organization->activity_report_language);
                        }
                    }
                    
                    return $ownerName . ' - ' . $ownerType . ' - ' . $languageLabel;
                }),

            Select::make(__('Report Type'), 'report_type')
                ->options([
                    ReportType::Annual->value => __('Annual'),
                    ReportType::Monthly->value => __('Monthly'),
                ])
                ->displayUsingLabels()
                ->rules('required')
                ->sortable()
                ->hideFromIndex(),

            Number::make(__('Year'), 'year')
                ->rules('required', 'integer', 'min:2000', 'max:' . (now()->year + 1))
                ->default(fn() => now()->year)
                ->sortable()
                ->hideFromIndex(),

            Number::make(__('Month'), 'month')
                ->rules('required_if:report_type,' . ReportType::Monthly->value, 'nullable', 'integer', 'min:1', 'max:12')
                ->hideFromIndex()
                ->dependsOn(['report_type'], function ($field, $request, $formData) {
                    if (isset($formData['report_type']) && $formData['report_type'] === ReportType::Monthly->value) {
                        $field->show();
                    } else {
                        $field->hide();
                    }
                }),

            Text::make(__('Period'), 'period_info')
                ->onlyOnIndex()
                ->displayUsing(function () {
                    $year = $this->year ?? '-';
                    
                    if ($this->report_type === ReportType::Annual) {
                        return $year . ' (yearly)';
                    } elseif ($this->report_type === ReportType::Monthly && $this->month) {
                        $monthFormatted = str_pad($this->month, 2, '0', STR_PAD_LEFT);
                        return $year . '-' . $monthFormatted;
                    }
                    
                    return $year;
                }),

            Text::make(__('PDF URL'), 'pdf_url')
                ->hideWhenCreating()
                ->hideWhenUpdating()
                ->displayUsing(function ($url) {
                    if (!$url) {
                        return '-';
                    }
                    // Extract filename from URL
                    $filename = basename(parse_url($url, PHP_URL_PATH));
                    return '<a href="' . $url . '" target="_blank" class="link-default">' . htmlspecialchars($filename) . '</a>';
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
        return [
            new \App\Nova\Filters\ActivityReportOwnerTypeFilter,
            new \App\Nova\Filters\ActivityReportReportTypeFilter,
            (new NovaSearchableBelongsToFilter(__('Customer')))->fieldAttribute('customer')->filterBy('customer_id'),
            (new NovaSearchableBelongsToFilter(__('Organization')))->fieldAttribute('organization')->filterBy('organization_id'),
            new \App\Nova\Filters\ActivityReportYearFilter,
            new \App\Nova\Filters\ActivityReportMonthFilter,
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
            new GenerateActivityReportPdf(),
        ];
    }
}


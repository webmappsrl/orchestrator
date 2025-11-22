<?php

namespace App\Nova\Filters;

use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

class ActivityReportMonthFilter extends Filter
{
    /**
     * The filter's component.
     *
     * @var string
     */
    public $component = 'select-filter';

    /**
     * Apply the filter to the given query.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  mixed  $value
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function apply(NovaRequest $request, $query, $value)
    {
        if ($value) {
            return $query->where('month', $value);
        }

        return $query;
    }

    /**
     * Get the filter's available options.
     * Shows months from activity reports.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function options(NovaRequest $request)
    {
        // Get distinct months from activity reports
        $months = \App\Models\ActivityReport::whereNotNull('month')
            ->distinct()
            ->orderBy('month')
            ->pluck('month');

        if ($months->isEmpty()) {
            return [];
        }

        // Month names in Italian
        $monthNames = [
            1 => __('January'),
            2 => __('February'),
            3 => __('March'),
            4 => __('April'),
            5 => __('May'),
            6 => __('June'),
            7 => __('July'),
            8 => __('August'),
            9 => __('September'),
            10 => __('October'),
            11 => __('November'),
            12 => __('December'),
        ];

        // Return months as options [month name => month number]
        return $months->mapWithKeys(function ($month) use ($monthNames) {
            $monthName = $monthNames[$month] ?? $month;
            return [$monthName => $month];
        })->toArray();
    }

    /**
     * Get the displayable name of the filter.
     *
     * @return string
     */
    public function name()
    {
        return __('Month');
    }
}

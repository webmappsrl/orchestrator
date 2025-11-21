<?php

namespace App\Nova\Filters;

use App\Models\Organization;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

class TicketReportOrganizationFilter extends Filter
{
    /**
     * The filter's component.
     *
     * @var string
     */
    public $component = 'select-filter';

    /**
     * The displayable name of the filter.
     *
     * @var string
     */
    public $name = 'Organization';

    /**
     * Apply the filter to the given query.
     * Filters stories by creator's organization
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  mixed  $value
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function apply(NovaRequest $request, $query, $value)
    {
        if ($value) {
            return $query->whereHas('creator.organizations', function ($q) use ($value) {
                $q->where('organizations.id', $value);
            });
        }

        return $query;
    }

    /**
     * Get the filter's available options.
     * Shows organizations of creators in the current filtered view
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function options(NovaRequest $request)
    {
        // Get organizations from creators in the current filtered index view
        try {
            $resourceClass = $request->resource();
            if (class_exists($resourceClass)) {
                $novaResource = new $resourceClass($request);
                if (method_exists($novaResource, 'indexQuery')) {
                    $query = $novaResource::indexQuery($request, \App\Models\Story::query());
                    
                    if ($query) {
                        // Get distinct creator_ids from the filtered index query
                        // Remove any existing orderBy to avoid PostgreSQL DISTINCT error
                        $creatorIds = $query->reorder()
                            ->select('creator_id')
                            ->distinct()
                            ->whereNotNull('creator_id')
                            ->pluck('creator_id');

                        if ($creatorIds->isNotEmpty()) {
                            // Get organizations from these creators
                            $organizationIds = \App\Models\User::whereIn('id', $creatorIds)
                                ->whereHas('organizations')
                                ->with('organizations')
                                ->get()
                                ->pluck('organizations')
                                ->flatten()
                                ->pluck('id')
                                ->unique()
                                ->toArray();

                            if (!empty($organizationIds)) {
                                return Organization::whereIn('id', $organizationIds)
                                    ->orderBy('name')
                                    ->pluck('id', 'name')
                                    ->toArray();
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // If there's an error, fall back to all organizations
        }

        // Fallback: return all organizations
        return Organization::orderBy('name')->get()->pluck('id', 'name')->toArray();
    }

    /**
     * Get the displayable name of the filter.
     *
     * @return string
     */
    public function name()
    {
        return __('Organization');
    }
}

<?php

namespace App\Nova\Filters;

use App\Models\Story;
use App\Models\User;
use App\Nova\CustomerStory;
use App\Nova\Story as NovaStory;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Illuminate\Support\Facades\DB;

class CreatorStoryFilter extends Filter
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
        return $query->where('creator_id', $value);
    }

    /**
     * Get the filter's available options.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function options(NovaRequest $request)
    {
        $resourceClass = $request->resource();
        if (class_exists($resourceClass)) {
            $novaResource = new $resourceClass($request);
            if (method_exists($novaResource, 'indexQuery')) {
                $query = $novaResource::indexQuery($request,  Story::query());
            }
        } else {
            $query = CustomerStory::indexQuery($request,  Story::query());
        }
        if ($query != null) {


            // Get distinct creator_ids from the filtered index query
            $creatorIds = $query->distinct()->pluck('creator_id');

            // Filter users to only those who are creators in the current index view
            return User::whereIn('id', $creatorIds)
                ->orderBy('name')
                ->pluck('id', 'name')
                ->toArray();
        } else {
            return User::pluck('id', 'name')->toArray();
        }
    }

    public function name()
    {
        return 'Creator';
    }
}

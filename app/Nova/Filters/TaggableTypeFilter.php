<?php

namespace App\Nova\Filters;

use App\Models\Story;
use App\Nova\CustomerStory;
use Illuminate\Http\Request;
use Laravel\Nova\Filters\Filter;
use Illuminate\Support\Facades\DB;

class TaggableTypeFilter extends Filter
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
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  mixed  $value
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function apply(Request $request, $query, $value)
    {
        return $query->whereHas('tags', function ($query) use ($value) {
            $query->where('tags.id', $value);
        });

        return $query;
    }

    /**
     * Get the filter's options.
     *
     * @return array
     */
    public function options(Request $request)
    {
        $resourceClass = $request->resource(); // "App\Nova\CustomerStory"

        if (class_exists($resourceClass)) {
            $novaResource = new $resourceClass($request);
            if (method_exists($novaResource, 'indexQuery')) {
                $query = $novaResource::indexQuery($request,  Story::query());
            }
        } else {
            $query = CustomerStory::indexQuery($request,  Story::query());
        }
        $tags =  $query
            ->join('taggables', 'taggables.taggable_id', '=', 'stories.id')
            ->join('tags', 'taggables.tag_id', '=', 'tags.id')
            ->where('taggables.taggable_type', Story::class)
            ->distinct()
            ->pluck('tags.id', 'tags.name')
            ->toArray();
        return $tags;
    }
}

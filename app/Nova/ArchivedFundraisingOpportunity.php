<?php

namespace App\Nova;

use Illuminate\Http\Request;
use Laravel\Nova\Http\Requests\NovaRequest;

class ArchivedFundraisingOpportunity extends FundraisingOpportunity
{
    /**
     * Get the displayable label of the resource.
     *
     * @return string
     */
    public static function label()
    {
        return __('Opportunities archiviate');
    }

    /**
     * Get the URI key for the resource.
     */
    public static function uriKey(): string
    {
        return 'archived-fundraising-opportunities';
    }

    /**
     * Build an "index" query for the given resource.
     * Mostra solo le opportunità scadute (deadline < now).
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function indexQuery(NovaRequest $request, $query)
    {
        return $query->where('deadline', '<', now());
    }

    /**
     * Determine if the user is authorized to create new resources.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    public static function authorizedToCreate(Request $request)
    {
        return false;
    }
}

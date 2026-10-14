<?php

namespace App\Nova\Filters;

use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Filtro della vista «I miei task» (oc:8722): task assegnati all'utente
 * loggato oppure creati da lui. Di default «Assegnati a me».
 */
class MyTaskScopeFilter extends Filter
{
    public const ASSIGNED = 'assigned';

    public const CREATED = 'created';

    public $component = 'select-filter';

    public function apply(NovaRequest $request, $query, $value)
    {
        $userId = $request->user()?->id;

        return match ($value) {
            self::ASSIGNED => $query->where('assignee_id', $userId),
            self::CREATED => $query->where('creator_id', $userId),
            default => $query,
        };
    }

    public function options(NovaRequest $request)
    {
        return [
            __('Assigned to me') => self::ASSIGNED,
            __('Created by me') => self::CREATED,
        ];
    }

    public function default()
    {
        return self::ASSIGNED;
    }

    public function name()
    {
        return __('Which tasks');
    }
}

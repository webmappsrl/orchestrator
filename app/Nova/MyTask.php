<?php

namespace App\Nova;

use App\Nova\Filters\MyTaskScopeFilter;
use App\Nova\Filters\TaskDueDateFilter;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Vista «I miei task» (oc:8722): i task assegnati all'utente loggato e
 * quelli creati da lui, per ogni ruolo. Il filtro MyTaskScopeFilter parte
 * da «Assegnati a me».
 */
class MyTask extends Task
{
    /**
     * Gli stessi task sono già cercabili tramite la resource Task: senza
     * questo la ricerca globale di Nova li mostrerebbe due volte.
     */
    public static $globallySearchable = false;

    public static function label()
    {
        return __('My tasks');
    }

    public static function singularLabel()
    {
        return __('Task');
    }

    public static function uriKey()
    {
        return 'my-tasks';
    }

    public static function indexQuery(NovaRequest $request, $query)
    {
        $user = $request->user();

        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->with(['quote.customer', 'assignee'])
            ->forUser($user)
            ->reorder('due_date', 'asc');
    }

    public function filters(NovaRequest $request)
    {
        return [
            new MyTaskScopeFilter(),
            new TaskDueDateFilter(),
        ];
    }
}

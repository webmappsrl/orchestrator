<?php

namespace Webmapp\KanbanCard\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

interface AppliesKanbanQuery
{
    /**
     * Apply additional constraints to the Kanban base query.
     *
     * @param  Builder  $query  Base query already scoped/filtered/searched (status still applied by controller).
     * @param  Request  $request
     * @param  string|null  $statusValue  The status currently being loaded (virtual statuses included).
     * @param  array  $config  Full card config sent by the frontend.
     * @return Builder
     */
    public function apply(Builder $query, Request $request, ?string $statusValue, array $config): Builder;
}


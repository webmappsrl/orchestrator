<?php

namespace Webmapp\KanbanCard\Contracts;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;

interface AggregatesKanbanColumn
{
    /**
     * Aggregate all items of a single column.
     *
     * @param  Collection<int,object>  $items  All models retrieved for the column.
     * @param  Request  $request
     * @param  string  $statusValue  The requested column status (virtual statuses included).
     * @param  array  $config  Full card config sent by the frontend.
     * @return mixed
     */
    public function aggregate(Collection $items, Request $request, string $statusValue, array $config);
}


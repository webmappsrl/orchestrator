<?php

namespace App\Nova\Filters;

use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

class TaskDueDateFilter extends Filter
{
    public $component = 'select-filter';

    public function apply(NovaRequest $request, $query, $value)
    {
        return match ($value) {
            'overdue' => $query->overdue(),
            'due_today' => $query->dueToday(),
            'upcoming' => $query->upcoming(),
            'completed' => $query->completedStatus(),
            default => $query,
        };
    }

    public function options(NovaRequest $request)
    {
        return [
            __('Overdue') => 'overdue',
            __('Due today') => 'due_today',
            __('Upcoming') => 'upcoming',
            __('Completed') => 'completed',
        ];
    }

    public function name()
    {
        return __('Due date');
    }
}

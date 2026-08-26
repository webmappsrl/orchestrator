<?php

namespace App\Nova\Filters;

use App\Models\User;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

class TaskAssigneeFilter extends Filter
{
    public $component = 'select-filter';

    public function apply(NovaRequest $request, $query, $value)
    {
        return $query->whereHas('quote', function ($q) use ($value) {
            $q->where('user_id', $value);
        });
    }

    public function options(NovaRequest $request)
    {
        return User::whereHas('quotes.tasks')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (User $user) => [$user->name => $user->id])
            ->all();
    }

    public function name()
    {
        return __('Assignee');
    }
}

<?php

namespace App\Nova;

use App\Enums\StoryStatus;
use Laravel\Nova\Http\Requests\NovaRequest;

class ArchivedDeadline extends Deadline
{

    public static function label()
    {
        return __('Archived deadlines');
    }

    public static function uriKey()
    {
        return 'archived-deadlines';
    }

    public static function indexQuery(NovaRequest $request, $query)
    {
        return $query->where('status', StoryStatus::Done);
    }
}

<?php

namespace App\Nova;

use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Resource;
use Laravel\Nova\Http\Requests\NovaRequest;
use Illuminate\Http\Request;

class StoryLog extends Resource
{
    public static $model = \App\Models\StoryLog::class;

    public static $title = 'id';

    public static $search = [
        'id',
    ];

    public function fields(NovaRequest $request)
    {
        return [
            BelongsTo::make('User', 'user', User::class)->sortable(),
            Date::make('Viewed At', 'viewed_at')->sortable(),
            Text::make('Changes', function () {
                $changes = $this->changes;
                if (is_array($changes)) {
                    return collect($changes)->map(function ($value, $key) {
                        return "<strong>{$key}:</strong> {$value}";
                    })->implode('<br>');
                }
                return '';
            })->asHtml(),
        ];
    }

    /**
     * Build an "index" query for the given resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function indexQuery(NovaRequest $request, $query)
    {
        return $query->orderBy('viewed_at', 'desc');
    }

    public static function authorizedToCreate(Request $request)
    {
        return false;
    }

    public function authorizedToUpdate(Request $request)
    {
        return false;
    }

    public function authorizedToDelete(Request $request)
    {
        return false;
    }

    public function authorizedToReplicate(Request $request)
    {
        return false;
    }

    public function authorizedToView(Request $request)
    {
        return false;
    }

    public static function searchable()
    {
        return false;
    }
}

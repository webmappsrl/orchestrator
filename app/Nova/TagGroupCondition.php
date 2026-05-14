<?php

namespace App\Nova;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;

class TagGroupCondition extends Resource
{
    public static $model = \App\Models\TagGroupCondition::class;

    public static $title = 'id';

    public static $search = ['id'];

    public static function availableForNavigation(Request $request): bool
    {
        return false;
    }

    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),

            BelongsTo::make('Tag', 'tag', \App\Nova\Tag::class)
                ->searchable()
                ->rules('required'),

            Number::make('Gruppo (AND index)', 'group_index')
                ->rules('required', 'integer', 'min:0')
                ->help('0 = primo gruppo OR, 1 = secondo gruppo AND, ecc.'),
        ];
    }

    public function cards(Request $request): array { return []; }
    public function filters(Request $request): array { return []; }
    public function lenses(Request $request): array { return []; }
    public function actions(Request $request): array { return []; }
}

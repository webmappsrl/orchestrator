<?php

namespace App\Nova;

use App\Traits\fieldTrait;
use App\Enums\DocumentationCategory;
use App\Enums\UserRole;
use App\Models\Story;
use App\Nova\Actions\ExportToPdf;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Resource;

class Documentation extends Resource
{
    use fieldTrait;

    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = \App\Models\Documentation::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'name';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id',
        'name'
    ];

    /**
     * Get the fields displayed by the resource.
     *
     * @param \Laravel\Nova\Http\Requests\NovaRequest $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [
            ID::make(__('ID'), 'id')->sortable(),
            $this->titleField('name')->readonly(false),
            $this->tagsField()->hideWhenCreating(),
            Select::make('Category', 'category')
                ->options(DocumentationCategory::labels())
                ->default(DocumentationCategory::Customer->value)
                ->sortable()
                ->rules('required'),
            $this->descriptionField($request),
        ];
    }

    public static function indexQuery(NovaRequest $request, $query)
    {
        if ($request->user()->hasRole(UserRole::Customer)) {
            $customerStories = Story::where('creator_id', $request->user()->id)->get();
            // Ottieni tutti i tag delle storie del cliente
            $customerStoryTags = $customerStories->flatMap(function ($story) {
                return $story->tags;
            });
            // Filtra i tag che hanno il tipo "documentation"
            $documentationIds = $customerStoryTags->filter(function ($tag) {
                if ($tag->getTaggableTypeAttribute() === 'Documentation') {
                    $documentation = \App\Models\Documentation::find($tag->taggable_id);
                    return $documentation->category == DocumentationCategory::Customer;
                } else {
                    return false;
                }
            })->pluck('taggable_id');

            return $query->whereIn('id', $documentationIds);
        }
        return $query;
    }


    public function actions(NovaRequest $request)
    {
        return [
            new ExportToPdf,
        ];
    }
}

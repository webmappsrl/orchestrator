<?php

namespace App\Nova;

use App\Enums\UserRole;
use Manogi\Tiptap\Tiptap;
use App\Traits\fieldTrait;
use Laravel\Nova\Resource;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Select;
use App\Nova\Actions\ExportToPdf;
use App\Enums\DocumentationCategory;
use Laravel\Nova\Http\Requests\NovaRequest;

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
            Text::make(__('PDF Download'), function () use ($request) {
                if (!$this->pdf_url) {
                    return '<span style="color: #999;">' . __('PDF not yet generated') . '</span>';
                }
                $filename = basename(parse_url($this->pdf_url, PHP_URL_PATH));
                // Show full filename in detail view, compact text in index view
                $linkText = $request->isResourceIndexRequest() ? '📄 ' . htmlspecialchars($filename) : htmlspecialchars($filename);
                return '<a href="' . htmlspecialchars($this->pdf_url) . '" class="link-default" target="_blank" download="' . htmlspecialchars($filename) . '">' . $linkText . '</a>';
            })
                ->asHtml()
                ->hideWhenCreating()
                ->hideWhenUpdating()
                ->sortable(false),
            $this->tagsField()->hideWhenCreating()->hideFromIndex(),
            Select::make('Category', 'category')
                ->options(DocumentationCategory::labels())
                ->default(DocumentationCategory::Customer->value)
                ->sortable()
                ->rules('required')
                ->hideFromIndex(function ($request) {
                    return $request->user()->hasRole(UserRole::Customer);
                }),
            Tiptap::make(__('Dev notes'), 'description')
                ->hideFromIndex()
                ->buttons($this->tiptapAllButtons)
                ->canSee($this->canSee('description'))
                ->help(__('Provide all the necessary information. You can add images using the "Add Image" option. If you\'d like to include a video, we recommend uploading it to a service like Google Drive, enabling link sharing, and pasting the link here. The more details you provide, the easier it will be for us to resolve the issue.'))
                ->alwaysShow()
        ];
    }

    public static function indexQuery(NovaRequest $request, $query)
    {
        if ($request->user()->hasRole(UserRole::Customer)) {
            return $query->where('category', DocumentationCategory::Customer->value);
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

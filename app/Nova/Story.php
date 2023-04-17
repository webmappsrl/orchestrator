<?php

namespace App\Nova;


use App\Models\Epic;
use App\Models\User;
use App\Enums\StoryStatus;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Status;
use Laravel\Nova\Fields\BelongsTo;
use App\Nova\Actions\MoveStoriesFromEpic;
use Datomatic\NovaMarkdownTui\MarkdownTui;
use Laravel\Nova\Http\Requests\NovaRequest;
use Datomatic\NovaMarkdownTui\Enums\EditorType;


class Story extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\Story>
     */
    public static $model = \App\Models\Story::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'name';

    /**
     * The number of resources to show per page via relationships.
     *
     * @var int
     */
    public static $perPageViaRelationship = 20;



    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id', 'name', 'description'
    ];

    /**
     * Get the fields displayed by the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [
            ID::make()->sortable(),
            Text::make(__('Name'), 'name')->sortable()
                ->displayUsing(function ($name, $a, $b) {
                    $wrappedName = wordwrap($name, 75, "\n", true);
                    $htmlName = str_replace("\n", '<br>', $wrappedName);
                    return $htmlName;
                })
                ->asHtml(),
            Select::make('Status')
                ->options(collect(StoryStatus::cases())
                    ->pluck('name', 'value'))
                ->default(StoryStatus::New->value)
                ->displayUsingLabels()
                ->hideFromIndex(),
            Status::make('Status')
                ->loadingWhen(['status' => 'progress'])
                ->failedWhen(['status' => 'rejected'])
                ->sortable(),
            MarkdownTui::make(__('Description'), 'description')
                ->hideFromIndex()
                ->initialEditType(EditorType::MARKDOWN),
            BelongsTo::make('User')->default(function ($request) {
                $epic = Epic::find($request->input('viaResourceId'));
                return $epic ? $epic->user_id : null;
            }),
            BelongsTo::make('Epic')->default(function ($request) {
                return $request->input('viaResourceId');
            }),
        ];
    }
    /**
     * Get the cards available for the request.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function cards(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the filters available for the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function filters(NovaRequest $request)
    {
        return [
            new filters\UserFilter,
            new filters\StoryStatusFilter,
        ];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function lenses(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function actions(NovaRequest $request)
    {
        return [
            (new actions\EditStoriesFromEpic)
                ->confirmText('Seleziona stato e utente da assegnare alle storie che hai selezionato. Clicca sul tasto "Conferma" per salvare o "Annulla" per annullare.')
                ->confirmButtonText('Conferma')
                ->cancelButtonText('Annulla'),

            (new actions\StoryToProgressStatusAction)
                ->showInline()
                ->confirmText('Clicca sul tasto "Conferma" per salvare lo status in Progress o "Annulla" per annullare.')
                ->confirmButtonText('Conferma')
                ->cancelButtonText('Annulla'),

            (new actions\StoryToDoneStatusAction)
                ->showInline()
                ->confirmText('Clicca sul tasto "Conferma" per salvare lo status in Done o "Annulla" per annullare.')
                ->confirmButtonText('Conferma')
                ->cancelButtonText('Annulla'),

            (new actions\StoryToTestStatusAction)
                ->showInline()
                ->confirmText('Clicca sul tasto "Conferma" per salvare lo status in Test o "Annulla" per annullare.')
                ->confirmButtonText('Conferma')
                ->cancelButtonText('Annulla'),

            (new actions\StoryToRejectedStatusAction)
                ->showInline()
                ->confirmText('Clicca sul tasto "Conferma" per salvare lo status in Rejected o "Annulla" per annullare.')
                ->confirmButtonText('Conferma')
                ->cancelButtonText('Annulla'),

            (new MoveStoriesFromEpic)
                ->confirmText('Seleziona l\'epica in cui vuoi spostare le storie selezionate. Clicca sul tasto "Conferma" per salvare o "Annulla" per annullare.')
                ->confirmButtonText('Conferma')
                ->cancelButtonText('Annulla'),
        ];
    }

    /**
     * Get the user that owns the Story
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'foreign_key', 'other_key');
    }

    public function indexBreadcrumb()
    {
        return null;
    }
}

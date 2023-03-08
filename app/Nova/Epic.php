<?php

namespace App\Nova;

use Laravel\Nova\Panel;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\BelongsTo;
use App\Nova\Actions\EpicDoneAction;
use App\Nova\Actions\EditEpicsFromIndex;
use Datomatic\NovaMarkdownTui\MarkdownTui;
use App\Nova\Actions\CreateStoriesFromText;
use Laravel\Nova\Http\Requests\NovaRequest;


class Epic extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\Epic>
     */
    public static $model = \App\Models\Epic::class;

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
    public static $perPageViaRelationship = 50;

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id', 'name', 'description', 'milestone.name', 'user.name', 'project.name', 'project.description', 'project.customer.name'
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

            new Panel('MAIN INFO', [
                ID::make()->sortable(),
                //display the relations in nova field
                BelongsTo::make('User'),
                BelongsTo::make('Milestone'),
                BelongsTo::make('Project')->searchable(),
                Text::make('Name')
                    ->sortable()
                    ->rules('required', 'max:255')
                    ->onlyOnIndex()
                    ->displayUsing(function ($name, $a, $b) {
                        $wrappedName = wordwrap($name, 50, "\n", true);
                        $htmlName = str_replace("\n", '<br>', $wrappedName);
                        return $htmlName;
                    })
                    ->asHtml(),
                Text::make('Name')
                    ->sortable()
                    ->rules('required', 'max:255')
                    ->hideFromIndex(),

                Text::make('SAL', function () {
                    return $this->wip();
                })->hideWhenCreating()->hideWhenUpdating(),
                Text::make('URL', 'pull_request_link')->nullable()->hideFromIndex()->displayUsing(function () {
                    return '<a class="link-default" target="_blank" href="' . $this->pull_request_link . '">' . $this->pull_request_link . '</a>';
                })->asHtml(),
                Text::make('Status')
                    ->hideWhenCreating()
                    ->hideWhenUpdating(),
            ]),


            new Panel('DESCRIPTION', [
                MarkdownTui::make('Description')
                    ->minHeight('500px'),

            ]),

            new Panel('NOTES', [
                MarkdownTui::make('Notes')
                    ->nullable()
                    ->minHeight('500px'),

            ]),

            HasMany::make('Stories'),
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
            new filters\MilestoneFilter,
            new filters\ProjectFilter,
            new filters\EpicStatusFilter
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
        return [
            new Lenses\MyEpicLens,
        ];
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
            (new CreateStoriesFromText)
                ->onlyOnDetail(),
            (new EpicDoneAction)
                ->onlyOnDetail(),
            (new EditEpicsFromIndex)
                ->confirmText('Seleziona stato, milestone, project e utente da assegnare alle epiche che hai selezionato. Clicca sul tasto "Conferma" per salvare o "Annulla" per annullare.')
                ->confirmButtonText('Conferma')
                ->cancelButtonText('Annulla'),
        ];
    }
}
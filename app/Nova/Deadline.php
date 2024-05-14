<?php

namespace App\Nova;

use Laravel\Nova\Fields\ID;
use App\Enums\DeadlineStatus;
use App\Enums\StoryStatus;
use App\Nova\Actions\EditDeadlinesAction;
use App\Nova\Filters\DeadlineStatusFilter;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Status;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\MorphToMany;
use Laravel\Nova\Http\Requests\NovaRequest;
use Datomatic\NovaMarkdownTui\MarkdownTui;
use Datomatic\NovaMarkdownTui\Enums\EditorType;
use Khalin\Nova4SearchableBelongsToFilter\NovaSearchableBelongsToFilter;

class Deadline extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\Deadline>
     */
    public static $model = \App\Models\Deadline::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'title';

    public static function label()
    {
        return __('Deadlines');
    }

    public static function uriKey()
    {
        return 'deadlines';
    }

    public function title()
    {
        $dueDate = $this->due_date->format('Y-m-d');
        return $dueDate . ' - ' . $this->title . ' (' . $this->customer->name . ')';
    }

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'due_date', 'status', 'title'
    ];

    public static function indexQuery(NovaRequest $request, $query)
    {
        return $query->where('status', '!=', StoryStatus::Done);
    }

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
            Date::make('Due Date', 'due_date')
                ->displayUsing(function ($value) {
                    return $value->format('Y-m-d');
                })
                ->sortable()
                ->rules('required', 'date'),
            Text::make('Title')->sortable(),
            MarkdownTui::make(__('Description'))
                ->initialEditType(EditorType::MARKDOWN)
                ->hideFromIndex(),
            //create a text field to show the link to the deadline-email view and render it as html
            Text::make('Email Template', function () {
                return '<a style="color:blue;" href="' . route('deadline.email', ['id' => $this->id]) . '" target="_blank">Template</a>';
            })->asHtml()->onlyOnDetail(),
            Select::make('Status')->options([
                'new' => DeadlineStatus::New,
                'progress' => DeadlineStatus::Progress,
                'done' => DeadlineStatus::Done,
                'expired' => DeadlineStatus::Expired,
            ])->default('new')
                ->hideFromDetail()
                ->hideFromIndex(),
            Status::make('Status')
                ->loadingWhen(['status' => 'new'])
                ->failedWhen(['status' => 'expired']),
            BelongsTo::make('Customer')->sortable()->searchable(),
            Text::make('Stories Count', function () {
                return $this->stories()->count();
            })->hideWhenCreating()->hideWhenUpdating(),
            Text::make('SAL', function () {
                return $this->wip();
            })->asHtml()->hideWhenCreating()->hideWhenUpdating(),
            MorphToMany::make('Stories')->searchable()->showCreateRelationButton(),
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
        $query = $this->indexQuery($request,  Deadline::query());
        return [
            (new Metrics\StoriesByField('status', 'Status', $query))->width('1/2'),
            (new Metrics\StoriesByUser('customer_id', 'Customer', $query))->width('1/2'),
        ];
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
            // new DeadlineCustomerFilter,
            (new NovaSearchableBelongsToFilter('Customer'))->fieldAttribute('customer')->filterBy('customer_id'),
            (new DeadlineStatusFilter)

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
            (new EditDeadlinesAction)
                ->confirmText('Are you sure you want to update the selected deadlines?')
                ->confirmButtonText('Update')
                ->cancelButtonText('Cancel')
                ->showInline()
        ];
    }

    public function indexBreadcrumb()
    {
        return null;
    }
}

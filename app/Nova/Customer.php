<?php

namespace App\Nova;

use Eminiarts\Tabs\Tab;
use Laravel\Nova\Panel;
use Eminiarts\Tabs\Tabs;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\Currency;
use Eminiarts\Tabs\Traits\HasTabs;
use Datomatic\NovaMarkdownTui\MarkdownTui;
use Laravel\Nova\Http\Requests\NovaRequest;
use App\Nova\Filters\CustomerWpMigrationFilter;
use Datomatic\NovaMarkdownTui\Enums\EditorType;
use Laravel\Nova\Fields\Textarea;

class Customer extends Resource
{
    use HasTabs;
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\Customer>
     */
    public static $model = \App\Models\Customer::class;

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
        'id', 'name', 'hs_id', 'domain_name', 'full_name', 'subscription_amount', 'subscription_last_payment', 'subscription_last_invoice', 'notes', 'acronym'
    ];

    /**
     * Get the fields displayed by the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        $title = 'Customer Details:' . $this->name;
        return [
            ID::make()->sortable(),
            Text::make('Name')
                ->sortable()
                ->rules('required', 'max:255')
                ->creationRules('unique:customers,name'),
            Text::make('Full Name', 'full_name')
                ->sortable()
                ->nullable()
                ->hideFromIndex(),
            Textarea::make('Heading', 'heading')
                ->nullable()
                ->hideFromIndex(),
            Text::make('Acronym', 'acronym')
                ->sortable()
                ->nullable(),
            Text::make('HS', 'hs_id')
                ->sortable()
                ->nullable()
                ->hideFromIndex(),
            Text::make('Domain Name', 'domain_name')
                ->sortable()
                ->nullable()
                ->hideFromIndex(),
            Select::make('WP Migration', 'wp_migration')->options(
                [
                    'wordpress' => 'Wordpress',
                    'geohub' => 'Geohub',
                    'geobox' => 'Geobox',
                ]
            )->sortable()->nullable(),
            MarkdownTui::make('Migration Note', 'migration_note')
                ->hideFromIndex()
                ->initialEditType(EditorType::MARKDOWN),
            Text::make('Contact emails', function () {
                if ($this->email == null) {
                    return null;
                }
                //get the mails by exploding the string by comma or space
                $mails = preg_split("/[\s,]+/", $this->email);
                //add a mailto link to each mail
                foreach ($mails as $key => $mail) {
                    $mails[$key] = "<a href='mailto:$mail'>$mail</a>";
                }
                //return the string as html
                return implode("<br>", $mails);
            })->asHtml(),
            Boolean::make('Subs.', 'has_subscription')
                ->sortable()
                ->nullable()->hideFromIndex(),
            Currency::make('S/Amount', 'subscription_amount')
                ->sortable()
                ->currency('EUR')
                ->nullable()->hideFromIndex(),
            Date::make('S/Payment', 'subscription_last_payment')
                ->sortable()
                ->nullable()->hideFromIndex(),
            Number::make('S/year', 'subscription_last_covered_year')
                ->sortable()
                ->nullable()
                ->rules('nullable', 'integer')->hideFromIndex(),
            Text::make('S/invoice', 'subscription_last_invoice')
                ->sortable()
                ->nullable()->hideFromIndex(),
            MarkdownTui::make('Notes', 'notes')
                ->initialEditType(EditorType::MARKDOWN)
                ->hideFromIndex(),
            new Tabs('Relationships', [
                Tab::make('Projects', [
                    HasMany::make('Projects', 'projects', Project::class),
                ]),
                Tab::make('Quotes', [
                    HasMany::make('Quotes', 'quotes', Quote::class),
                ]),
                Tab::make('Deadlines', [
                    HasMany::make('Deadlines', 'deadlines', Deadline::class),
                ]),

            ])

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
            new CustomerWpMigrationFilter,
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
        return [];
    }

    public function indexBreadcrumb()
    {
        return null;
    }
}

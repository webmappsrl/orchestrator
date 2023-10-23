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
use Illuminate\Database\Eloquent\Model;
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
        'name', 'domain_name', 'full_name', 'acronym', 'email'
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
            Text::make('Customer', function () {
                $string = '';
                $name = $this->name;
                $fullName = $this->full_name;
                $emails = $this->email;
                $acronym = $this->acronym;

                if (isset($name) & isset($acronym)) {
                    $string .= $name . ' (' . $acronym . ')';
                } else if (isset($name)) {
                    $string .= $name;
                }
                if (isset($fullName)) {
                    $fullName = wordwrap($fullName, 40, "\n", true);
                    $fullName = explode("\n", $fullName);
                    $fullName = implode("</br>", $fullName);
                    $string .= '</br>' . $fullName;
                }
                if (isset($emails)) {
                    //get the mails by exploding the string by comma or space
                    $mails = preg_split("/[\s,]+/", $this->email);
                    //add a mailto link to each mail
                    foreach ($mails as $key => $mail) {
                        $mails[$key] = "<a style='color:blue;' href='mailto:$mail'>$mail</a>";
                    }
                    $mails = implode(", </br>", $mails);
                    $string .= '</br> ' . $mails;
                }
                return $string;
            })->asHtml()
                ->hideWhenCreating()
                ->hideWhenUpdating()
                ->showOnPreview(function (NovaRequest $request, $resource) {
                    return $resource->exists;
                }),
            Text::make('Scores', function () {
                $string = '';
                $scoreCash = $this->score_cash;
                $scorePain = $this->score_pain;
                $scoreBusiness = $this->score_business;
                if (isset($scoreCash)) {
                    $string .= 'Cash: ' . $scoreCash;
                }
                if (isset($scorePain)) {
                    $string .= '</br> Pain: ' . $scorePain;
                }
                if (isset($scoreBusiness)) {
                    $string .= '</br> Business: ' . $scoreBusiness;
                }
                return $string;
            })->asHtml()
                ->hideWhenCreating()
                ->hideWhenUpdating(),
            Number::make('Score', 'score')
                ->sortable()
                ->nullable()
                ->hideWhenCreating()
                ->hideWhenUpdating(),
            Text::make('Name')
                ->sortable()
                ->rules('required', 'max:255')
                ->creationRules('unique:customers,name')
                ->onlyOnForms(),
            Text::make('Full Name', 'full_name')
                ->sortable()
                ->nullable()
                ->onlyOnForms(),
            Textarea::make('Heading', 'heading')
                ->nullable()
                ->hideFromIndex(),
            Text::make('Acronym', 'acronym')
                ->sortable()
                ->nullable()
                ->onlyOnForms(),
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
            Text::make('Contact emails', 'email')
                ->onlyOnForms(),
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

            ]),
            Number::make('Score Cash', 'score_cash')
                ->sortable()
                ->nullable()
                ->onlyOnForms(),
            Number::make('Score Pain', 'score_pain')
                ->sortable()
                ->nullable()
                ->onlyOnForms(),
            Number::make('Score Business', 'score_business')
                ->sortable()
                ->nullable()
                ->onlyOnForms(),

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

    public static function afterCreate(NovaRequest $request, Model $model)
    {
        $model->score = $model->score_cash + $model->score_pain + $model->score_business;
        if ($model->score == null) {
            $model->score = 0;
        }
        $model->save();
    }

    public static function afterUpdate(NovaRequest $request, Model $model)
    {
        $model->score = $model->score_cash + $model->score_pain + $model->score_business;
        if ($model->score == null) {
            $model->score = 0;
        }
        $model->save();
    }
}

<?php

namespace App\Nova;

use App\Models\Tag;
use App\Nova\Metrics\TagGroupTicketsByStatus;
use App\Nova\Metrics\TagGroupTicketsByType;
use App\Nova\Metrics\TagSal;
use Datomatic\NovaMarkdownTui\Enums\EditorType;
use Datomatic\NovaMarkdownTui\MarkdownTui;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\ID;
use Outl1ne\MultiselectField\Multiselect as MultiSelect;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Panel;

class TagGroup extends Resource
{
    public static $model = \App\Models\TagGroup::class;

    public static $title = 'name';

    public static $search = ['id', 'name'];

    public function fields(NovaRequest $request): array
    {
        if ($this->model()) {
            if ($request->isResourceDetailRequest()) {
                $this->prepareModelForDetailView($request);
            }
            if ($request->isResourceIndexRequest()) {
                $this->prepareModelForIndexView($request);
            }
        }

        $tagOptions = Tag::whereNull('taggable_type')
            ->orWhere('taggable_type', '!=', \App\Models\Documentation::class)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();

        return [
            ID::make()->sortable(),

            Text::make('Nome', 'name')
                ->sortable()
                ->rules('required', 'max:255'),

            Text::make('SAL #')->resolveUsing(function () {
                [$closed, $total] = $this->salTicketCounts();
                return '<span style="font-weight:bold;">['.$closed.']/['.$total.']</span>';
            })->asHtml()->onlyOnIndex(),

            Text::make('SAL t')->resolveUsing(function () {
                $empty = __('Empty');
                $totalHours = $this->getTotalHoursAttribute() ?? $empty;
                $estimate = $this->estimate ?? $empty;
                $color = $this->isClosed() ? 'green' : 'orange';
                $salPercentage = $this->calculateSalPercentage();
                $trend = $salPercentage >= 100 ? '😡' : '😊';

                if (!$this->getTotalHoursAttribute() && !$this->estimate) {
                    return "<a style=\"font-weight:bold;\"> $empty </a>";
                }
                if (!$this->getTotalHoursAttribute() || !$this->estimate) {
                    return "<a style=\"font-weight:bold;\"> $totalHours / $estimate </a>";
                }
                return "$trend <a style=\"color:{$color}; font-weight:bold;\"> $totalHours / $estimate </a> <a style=\"color:{$color}; font-weight:bold;\"> [$salPercentage%] </a>";
            })->asHtml()->onlyOnIndex(),


            MarkdownTui::make('Descrizione', 'description')
                ->nullable()
                ->hideFromIndex()
                ->initialEditType(EditorType::MARKDOWN),

            ...$this->conditionFieldsForIndex($tagOptions),

            Panel::make('Filtri', [
                Text::make('', function () {
                    return '<div style="padding: 10px 0; color: #6B7280; font-size: 0.875rem; line-height: 1.5;">
                        Un ticket viene incluso solo se ha <strong>almeno un tag da ogni gruppo compilato</strong>.<br>
                        Puoi selezionare più tag nello stesso gruppo: basta che il ticket ne abbia almeno uno.<br>
                        Lascia un gruppo vuoto per non applicare quel filtro.
                    </div>';
                })->asHtml()->hideFromIndex()->readonly(),

                MultiSelect::make('Gruppo 1', 'condition_1')
                    ->options($tagOptions)
                    ->nullable()
                    ->hideFromIndex()
                    ->help('Il ticket deve avere almeno uno di questi tag.'),

                MultiSelect::make('Gruppo 2', 'condition_2')
                    ->options($tagOptions)
                    ->nullable()
                    ->hideFromIndex()
                    ->help('Il ticket deve avere almeno uno di questi tag.'),

                MultiSelect::make('Gruppo 3', 'condition_3')
                    ->options($tagOptions)
                    ->nullable()
                    ->hideFromIndex()
                    ->help('Il ticket deve avere almeno uno di questi tag.'),

                MultiSelect::make('Gruppo 4', 'condition_4')
                    ->options($tagOptions)
                    ->nullable()
                    ->hideFromIndex()
                    ->help('Il ticket deve avere almeno uno di questi tag.'),
            ]),

            BelongsToMany::make('Story', 'stories', \App\Nova\Story::class)
                ->onlyOnDetail(),
        ];
    }

    private function conditionFieldsForIndex(array $tagOptions): array
    {
        $fields = [];

        foreach (['condition_1', 'condition_2', 'condition_3', 'condition_4'] as $i => $slot) {
            $label = 'Gruppo ' . ($i + 1);
            $fields[] = Text::make($label, function () use ($slot, $tagOptions) {
                $ids = $this->{$slot} ?? [];
                if (empty($ids)) {
                    return '—';
                }
                return collect($ids)
                    ->map(fn ($id) => $tagOptions[$id] ?? "#{$id}")
                    ->implode(', ');
            })->asHtml()->onlyOnIndex();
        }

        return $fields;
    }

    private function prepareModelForDetailView(NovaRequest $request): void
    {
        $this->model()->syncConditionsFromSlots();
        $this->model()->syncStories();
    }

    private function prepareModelForIndexView(NovaRequest $request): void
    {
        $this->model()->syncStories();
    }

    public function cards(NovaRequest $request): array
    {
        return [
            (new TagSal())->onlyOnDetail(),
            (new TagGroupTicketsByStatus())->onlyOnDetail(),
            (new TagGroupTicketsByType())->onlyOnDetail(),
        ];
    }
    public function filters(NovaRequest $request): array { return []; }
    public function lenses(NovaRequest $request): array { return []; }
    public function actions(NovaRequest $request): array { return []; }
}

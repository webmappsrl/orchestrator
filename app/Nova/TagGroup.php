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

        $tagGroupOptions = \App\Models\TagGroup::when(
                $this->resource->id ?? null,
                fn ($q) => $q->where('id', '!=', $this->resource->id)
            )
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn ($g) => ['g:' . $g->id => 'tg: ' . $g->name])
            ->toArray();

        $mergedOptions = collect($tagOptions)
            ->mapWithKeys(fn ($name, $id) => ['t:' . $id => $name])
            ->merge($tagGroupOptions)
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
                        Un ticket viene incluso solo se soddisfa <strong>tutti i gruppi compilati</strong>.<br>
                        In ogni gruppo puoi mettere tag e/o Tag Group (prefisso <strong>tg:</strong>): basta che il ticket ne abbia almeno uno.<br>
                        Lascia un gruppo vuoto per non applicare quel filtro.
                    </div>';
                })->asHtml()->hideFromIndex()->readonly(),

                MultiSelect::make('Gruppo 1', 'condition_1')
                    ->options($mergedOptions)->nullable()->hideFromIndex()
                    ->help('Tag e/o Tag Group in OR. Il ticket deve corrispondere ad almeno uno.'),

                MultiSelect::make('Gruppo 2', 'condition_2')
                    ->options($mergedOptions)->nullable()->hideFromIndex()
                    ->help('Tag e/o Tag Group in OR. Il ticket deve corrispondere ad almeno uno.'),

                MultiSelect::make('Gruppo 3', 'condition_3')
                    ->options($mergedOptions)->nullable()->hideFromIndex()
                    ->help('Tag e/o Tag Group in OR. Il ticket deve corrispondere ad almeno uno.'),

                MultiSelect::make('Gruppo 4', 'condition_4')
                    ->options($mergedOptions)->nullable()->hideFromIndex()
                    ->help('Tag e/o Tag Group in OR. Il ticket deve corrispondere ad almeno uno.'),
            ]),

            BelongsToMany::make('Story', 'stories', \App\Nova\Story::class)
                ->onlyOnDetail(),
        ];
    }

    private function conditionFieldsForIndex(array $tagOptions): array
    {
        $tagGroupMap = \App\Models\TagGroup::orderBy('name')->pluck('name', 'id')->toArray();
        $fields = [];

        foreach (['condition_1', 'condition_2', 'condition_3', 'condition_4'] as $i => $slot) {
            $label = 'Gruppo ' . ($i + 1);

            $fields[] = Text::make($label, function () use ($slot, $tagOptions, $tagGroupMap) {
                $parts = collect($this->{$slot} ?? [])->map(function ($value) use ($tagOptions, $tagGroupMap) {
                    $value = (string) $value;
                    if (str_starts_with($value, 'g:')) {
                        $id = (int) substr($value, 2);
                        return 'tg: ' . ($tagGroupMap[$id] ?? "#{$id}");
                    }
                    $id = str_starts_with($value, 't:') ? (int) substr($value, 2) : (int) $value;
                    return $tagOptions[$id] ?? "#{$id}";
                });

                return $parts->isEmpty() ? '—' : $parts->implode(', ');
            })->asHtml()->onlyOnIndex();
        }

        return $fields;
    }

    private function prepareModelForDetailView(NovaRequest $request): void
    {
        $this->syncWithDependencies($this->model());
    }

    private function prepareModelForIndexView(NovaRequest $request): void
    {
        $this->syncWithDependencies($this->model());
    }

    private function syncWithDependencies(\App\Models\TagGroup $model): void
    {
        $model->syncConditionsFromSlots();

        $refIds = $model->conditions()->whereNotNull('ref_tag_group_id')->pluck('ref_tag_group_id');
        if ($refIds->isNotEmpty()) {
            \App\Models\TagGroup::whereIn('id', $refIds)->get()->each(function ($nested) {
                $nested->syncConditionsFromSlots();
                $nested->syncStories();
            });
        }

        $model->syncStories();
        $model->unsetRelation('stories');
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

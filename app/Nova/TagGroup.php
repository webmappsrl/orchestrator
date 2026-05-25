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
            fn($q) => $q->where('id', '!=', $this->resource->id)
        )
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn($g) => ['g:' . $g->id => 'tg: ' . $g->name])
            ->toArray();

        $mergedOptions = collect($tagOptions)
            ->mapWithKeys(fn($name, $id) => ['t:' . $id => $name])
            ->merge($tagGroupOptions)
            ->toArray();

        return [
            ID::make()->sortable(),

            Text::make('Nome', 'name')
                ->sortable()
                ->rules('required', 'max:255'),

            Text::make('Stato', function () {
                $stories = $this->stories()->get();
                $total = $stories->count();
                if ($total === 0) {
                    return '—';
                }
                $closed = $stories->whereIn('status', \App\Models\Tag::salClosedStoryStatusValues())->count();
                $pct = round(($closed / $total) * 100);
                $color = $pct >= 100 ? '#16A34A' : ($pct >= 50 ? '#EAB308' : '#6B7280');
                $bar = $this->renderStatusBar($stories, 160);
                $badge = "<span style=\"font-weight:bold;color:{$color};min-width:36px;text-align:right;\">{$pct}%</span>";
                return "<div style=\"display:flex;align-items:center;gap:8px;\">{$bar}{$badge}</div>";
            })->asHtml()->onlyOnIndex(),

            MarkdownTui::make('Descrizione', 'description')
                ->nullable()
                ->hideFromIndex()
                ->initialEditType(EditorType::MARKDOWN),

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
            })->asHtml()->exceptOnForms(),



            Text::make('Stato', function () {
                $stories = $this->stories()->get();
                $total = $stories->count();
                if ($total === 0) {
                    return '—';
                }
                $closed = $stories->whereIn('status', \App\Models\Tag::salClosedStoryStatusValues())->count();
                $pct = round(($closed / $total) * 100);
                $color = $pct >= 100 ? '#16A34A' : ($pct >= 50 ? '#EAB308' : '#6B7280');
                $bar = $this->renderStatusBar($stories, 300);
                $badge = "<span style=\"font-weight:bold;color:{$color};\">{$pct}% — {$closed}/{$total} tickets chiusi</span>";
                return "<div style=\"display:flex;align-items:center;gap:12px;\">{$bar}{$badge}</div>";
            })->asHtml()->onlyOnDetail(),

            ...$this->conditionFieldsForIndex($tagOptions),

            Panel::make('Filtri', array_merge(
                [
                    Text::make('', function () {
                        return '<div style="padding: 10px 0; color: #6B7280; font-size: 0.875rem; line-height: 1.5;">
                            Un ticket viene incluso solo se soddisfa <strong>tutti i gruppi compilati</strong>.<br>
                            In ogni gruppo puoi mettere tag e/o Tag Group (prefisso <strong>tg:</strong>): basta che il ticket ne abbia almeno uno.<br>
                            Lascia un gruppo vuoto per non applicare quel filtro.
                        </div>';
                    })->asHtml()->hideFromIndex()->readonly(),
                ],
                $this->conditionPanelFields($tagOptions, $mergedOptions)
            )),

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

    private function renderStatusBar(\Illuminate\Support\Collection $stories, int $width = 160): string
    {
        if ($stories->isEmpty()) {
            return '—';
        }

        $total = $stories->count();
        $grouped = $stories->groupBy('status');
        $segments = '';
        $tooltipParts = [];

        foreach (\App\Enums\StoryStatus::cases() as $status) {
            $count = $grouped->get($status->value)?->count() ?? 0;
            if ($count === 0) {
                continue;
            }
            $pct = round(($count / $total) * 100, 2);
            $color = $status->color();
            $segments .= "<div style=\"width:{$pct}%;background:{$color};\"></div>";
            $tooltipParts[] = $status->label() . ': ' . $count;
        }

        $tooltip = implode(' | ', $tooltipParts);

        return "<div title=\"{$tooltip}\" style=\"display:flex;width:{$width}px;height:14px;border-radius:4px;overflow:hidden;gap:1px;\">{$segments}</div>";
    }

    private function renderConditionRowForDetail(string $value, array $tagOptions, array $tagGroupMap): string
    {
        if (str_starts_with($value, 'g:')) {
            $id = (int) substr($value, 2);
            $name = 'tg: ' . ($tagGroupMap[$id] ?? "#{$id}");
            $model = \App\Models\TagGroup::find($id);
            $stories = $model ? $model->stories()->get() : collect();
            $url = '/resources/tag-groups/' . $id;
        } else {
            $id = str_starts_with($value, 't:') ? (int) substr($value, 2) : (int) $value;
            $name = $tagOptions[$id] ?? "#{$id}";
            $model = \App\Models\Tag::find($id);
            $stories = \App\Models\Story::whereHas('tags', fn($q) => $q->where('tags.id', $id))->get();
            $url = '/resources/tags/' . $id;
        }

        $total = $stories->count();
        $bar = $total > 0 ? $this->renderStatusBar($stories, 120) : '';
        $countLabel = $total > 0 ? "<span style=\"color:#6B7280;font-size:0.8rem;\">{$total} tickets</span>" : '';
        $salLabel = $this->renderSalLabel($model);

        return "<a href=\"{$url}\" target=\"_blank\" style=\"display:flex;align-items:center;gap:12px;padding:4px 0;text-decoration:none;color:inherit;\">
            <span style=\"width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;\" title=\"{$name}\">{$name}</span>
            {$bar}
            {$countLabel}
            {$salLabel}
        </a>";
    }

    private function renderSalLabel(?\App\Models\Tag $model): string
    {
        if (! $model) {
            return '';
        }

        $actual   = $model->total_hours;
        $estimate = $model->estimate;

        if ($actual === null && $estimate === null) {
            return '';
        }

        $format = fn($v) => $v === null
            ? '—'
            : rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.') . 'h';

        $a = $format($actual);
        $e = $format($estimate);
        $pct = ($actual && $estimate) ? ' [' . round($actual / $estimate * 100) . '%]' : '';

        return "<span style=\"color:#6B7280;font-size:0.8rem;font-weight:600;\">{$a} / {$e}{$pct}</span>";
    }

    private function conditionPanelFields(array $tagOptions, array $mergedOptions): array
    {
        $tagGroupMap = \App\Models\TagGroup::orderBy('name')->pluck('name', 'id')->toArray();
        $fields = [];

        foreach (['condition_1', 'condition_2', 'condition_3', 'condition_4'] as $i => $slot) {
            $label = 'Gruppo ' . ($i + 1);

            $fields[] = Text::make($label, function () use ($slot, $tagOptions, $tagGroupMap) {
                $values = $this->{$slot} ?? [];
                if (empty($values)) {
                    return '—';
                }
                return collect($values)->map(
                    fn($v) =>
                    $this->renderConditionRowForDetail((string) $v, $tagOptions, $tagGroupMap)
                )->implode('');
            })->asHtml()->onlyOnDetail();

            $fields[] = MultiSelect::make($label, $slot)
                ->options($mergedOptions)->nullable()->onlyOnForms()
                ->help('Tag e/o Tag Group in OR. Il ticket deve corrispondere ad almeno uno.');
        }

        return $fields;
    }

    public function cards(NovaRequest $request): array
    {
        return [
            (new TagSal())->onlyOnDetail(),
            (new TagGroupTicketsByStatus())->onlyOnDetail(),
            (new TagGroupTicketsByType())->onlyOnDetail(),
        ];
    }
    public function filters(NovaRequest $request): array
    {
        return [];
    }
    public function lenses(NovaRequest $request): array
    {
        return [];
    }
    public function actions(NovaRequest $request): array
    {
        return [];
    }
}

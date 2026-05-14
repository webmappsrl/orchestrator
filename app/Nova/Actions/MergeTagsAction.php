<?php

namespace App\Nova\Actions;

use App\Models\Tag;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Http\Requests\NovaRequest;

class MergeTagsAction extends Action
{
    public $name = 'Merge Tags';
    public $confirmButtonText = 'Esegui Merge';

    public function handle(ActionFields $fields, Collection $models): void
    {
        $destinationId = (int) $fields->destination_tag_id;
        $destination = Tag::find($destinationId);

        if (! $destination) {
            $this->danger('Tag destinazione non trovato.');
            return;
        }

        $merged = 0;

        foreach ($models as $source) {
            if ($source->id === $destinationId) {
                continue;
            }

            $source->tagged()->each(function ($story) use ($destination) {
                if (! $story->tags()->where('tags.id', $destination->id)->exists()) {
                    $story->tags()->attach($destination->id);
                }
            });

            $merged++;
        }

        $this->message("Merge completato. {$merged} tag sorgente processati.");
    }

    public function fields(NovaRequest $request): array
    {
        $selectedIds = collect($request->resources ?? [])->filter()->map(fn($id) => (int) $id)->all();

        return [
            Select::make('Tag destinazione', 'destination_tag_id')
                ->options(
                    Tag::where(function ($q) {
                        $q->whereNull('taggable_type')
                          ->orWhere('taggable_type', '!=', \App\Models\Documentation::class);
                    })
                    ->when($selectedIds, fn($q) => $q->whereNotIn('id', $selectedIds))
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->toArray()
                )
                ->rules('required')
                ->searchable(),
        ];
    }
}

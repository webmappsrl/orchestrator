<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TagGroup extends Tag
{
    use HasFactory;

    protected $table = 'tag_groups';

    protected $fillable = [
        'name',
        'description',
        'condition_1',
        'condition_2',
        'condition_3',
        'condition_4',
    ];


    protected $casts = [
        'condition_1' => 'array',
        'condition_2' => 'array',
        'condition_3' => 'array',
        'condition_4' => 'array',
    ];

    public function getEstimateAttribute(): ?float
    {
        $sum = $this->stories()->sum('estimated_hours');
        return $sum > 0 ? $sum : null;
    }

    public function tagged(): BelongsToMany
    {
        return $this->stories();
    }

    public function conditions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TagGroupCondition::class);
    }

    public function stories(): BelongsToMany
    {
        return $this->belongsToMany(Story::class, 'tag_group_stories');
    }

    /**
     * Sincronizza tag_group_conditions dai 4 array JSON.
     * Ogni slot non vuoto diventa un gruppo AND (group_index 0..3) con N tag in OR.
     */
    public function syncConditionsFromSlots(): void
    {
        $this->conditions()->whereIn('group_index', [0, 1, 2, 3])->delete();

        foreach (['condition_1', 'condition_2', 'condition_3', 'condition_4'] as $index => $slot) {
            $tagIds = $this->{$slot} ?? [];
            foreach ($tagIds as $tagId) {
                TagGroupCondition::create([
                    'tag_group_id' => $this->id,
                    'tag_id'       => (int) $tagId,
                    'group_index'  => $index,
                ]);
            }
        }
    }

    public function syncStories(): void
    {
        $this->stories()->sync($this->computeMatchingStoryIds());
    }

    public function syncForStory(Story $story): void
    {
        if ($this->storyMatches($story)) {
            $this->stories()->syncWithoutDetaching([$story->id]);
        } else {
            $this->stories()->detach($story->id);
        }
    }

    public function storyMatches(Story $story): bool
    {
        $groups = $this->conditions()->get()->groupBy('group_index');

        if ($groups->isEmpty()) {
            return false;
        }

        $storyTagIds = $story->tags()->pluck('tags.id');

        foreach ($groups as $conditions) {
            if ($storyTagIds->intersect($conditions->pluck('tag_id'))->isEmpty()) {
                return false;
            }
        }

        return true;
    }

    private function computeMatchingStoryIds(): array
    {
        $groups = $this->conditions()->get()->groupBy('group_index');

        if ($groups->isEmpty()) {
            return [];
        }

        $query = Story::query();

        foreach ($groups as $conditions) {
            $tagIds = $conditions->pluck('tag_id');
            $query->whereHas('tags', function (Builder $q) use ($tagIds) {
                $q->whereIn('tags.id', $tagIds);
            });
        }

        return $query->pluck('id')->toArray();
    }
}

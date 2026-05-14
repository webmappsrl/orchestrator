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

    public function syncConditionsFromSlots(): void
    {
        $this->conditions()->whereIn('group_index', [0, 1, 2, 3])->delete();

        foreach ([0, 1, 2, 3] as $index) {
            $slot = 'condition_' . ($index + 1);

            foreach ($this->{$slot} ?? [] as $value) {
                $value = (string) $value;

                if (str_starts_with($value, 'g:')) {
                    TagGroupCondition::create([
                        'tag_group_id'     => $this->id,
                        'ref_tag_group_id' => (int) substr($value, 2),
                        'group_index'      => $index,
                    ]);
                } else {
                    $tagId = str_starts_with($value, 't:') ? (int) substr($value, 2) : (int) $value;
                    TagGroupCondition::create([
                        'tag_group_id' => $this->id,
                        'tag_id'       => $tagId,
                        'group_index'  => $index,
                    ]);
                }
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
            $tagIds      = $conditions->whereNotNull('tag_id')->pluck('tag_id');
            $refGroupIds = $conditions->whereNotNull('ref_tag_group_id')->pluck('ref_tag_group_id');

            $matchesTag   = $tagIds->isNotEmpty() && $storyTagIds->intersect($tagIds)->isNotEmpty();
            $matchesGroup = TagGroup::whereIn('id', $refGroupIds)
                ->get()
                ->contains(fn ($g) => $g->stories()->where('stories.id', $story->id)->exists());

            if (! $matchesTag && ! $matchesGroup) {
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
            $tagIds      = $conditions->whereNotNull('tag_id')->pluck('tag_id');
            $refGroupIds = $conditions->whereNotNull('ref_tag_group_id')->pluck('ref_tag_group_id');

            $nestedStoryIds = TagGroup::whereIn('id', $refGroupIds)
                ->get()
                ->flatMap(fn ($g) => $g->stories()->pluck('stories.id'))
                ->unique()
                ->values();

            $query->where(function (Builder $q) use ($tagIds, $nestedStoryIds) {
                if ($tagIds->isNotEmpty()) {
                    $q->orWhereHas('tags', function (Builder $inner) use ($tagIds) {
                        $inner->whereIn('tags.id', $tagIds);
                    });
                }
                if ($nestedStoryIds->isNotEmpty()) {
                    $q->orWhereIn('id', $nestedStoryIds);
                }
            });
        }

        return $query->pluck('id')->toArray();
    }
}

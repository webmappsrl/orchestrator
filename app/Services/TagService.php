<?php

namespace App\Services;

use App\Models\Story;
use App\Models\Tag;
use Illuminate\Support\Collection;

class TagService
{
    public function ensureTag(string $name, array $attributes = []): Tag
    {
        return Tag::firstOrCreate(
            ['name' => $name],
            array_merge(['name' => $name], $attributes)
        );
    }

    public function attachTagToStory(Story $story, Tag $tag): void
    {
        if (! $story->tags()->where('tags.id', $tag->id)->exists()) {
            $story->tags()->attach($tag->id);
        }
    }

    public function attachTagsFromTextToStory(Story $story): void
    {
        $text = ($story->description ?? '') . ' ' . ($story->customer_request ?? '');
        $repoNames = static::extractRepoNamesFromText($text);

        foreach ($repoNames as $repoName) {
            $tag = $this->ensureTag($repoName);
            $this->attachTagToStory($story, $tag);
        }
    }

    public function attachQuarterTagToStory(Story $story): void
    {
        $tag = $this->ensureTag(static::currentQuarterName());
        $this->attachTagToStory($story, $tag);
    }

    public function attachCustomerTagToStory(Story $story): void
    {
        if (! $story->creator_id) {
            return;
        }

        $creator = \App\Models\User::find($story->creator_id);
        if (! $creator) {
            return;
        }

        $customer = $creator->associatedCustomer;
        if (! $customer) {
            return;
        }

        $customerTag = $customer->tags()->first();
        if ($customerTag) {
            $this->attachTagToStory($story, $customerTag);
        }
    }

    public static function currentQuarterName(): string
    {
        $year = now()->format('y');
        $quarter = (int) ceil(now()->month / 3);
        return "{$year}Q{$quarter}";
    }

    public static function extractRepoNamesFromText(string $text): Collection
    {
        preg_match_all(
            '#https?://(?:github|gitlab)\.com/[^/]+/([a-zA-Z0-9_\-\.]+)#',
            $text,
            $matches
        );

        return collect($matches[1])->unique()->values();
    }
}

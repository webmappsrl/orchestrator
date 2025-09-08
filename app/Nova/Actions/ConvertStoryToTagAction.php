<?php

namespace App\Nova\Actions;

use App\Enums\StoryStatus;
use App\Models\Project;
use App\Models\Story;
use App\Models\Tag;
use Datomatic\NovaMarkdownTui\Enums\EditorType;
use Datomatic\NovaMarkdownTui\MarkdownTui;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class ConvertStoryToTagAction extends Action
{
    use InteractsWithQueue, Queueable;

    /**
     * Perform the action on the given models.
     *
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $createdTag = [];
        try {
            foreach ($models as $story) {
                $description = $fields['description'] ?: $story->description;
                if (! $fields['description'] && $description && $this->containsHtml($description)) {
                    $description = $this->convertHtmlToMarkdown($description);
                }

                $tag = Tag::create([
                    'name' => $fields['tag_name'] ?: $story->name,
                    'description' => $description,
                    'estimate' => $fields['estimate'] ?: $story->hours,
                    'taggable_type' => Project::class,
                    'taggable_id' => $story->project_id,
                ]);

                $story->tags()->syncWithoutDetaching([$tag->id]);

                if ($story->status !== StoryStatus::Done->value) {
                    $story->update([
                        'status' => StoryStatus::Done->value,
                    ]);
                }

                $createdTag[] = $tag;
            }
        } catch (\Throwable $e) {
            return Action::danger('Errore: '.$e->getMessage());
        }

        if (count($createdTag) === 1) {
            $tag = $createdTag[0];

            return Action::visit('/resources/tags/'.$tag->id)
                ->message(__('Ticket converted to tag')."'{$tag->name}'");
        }
    }

    /**
     * Get the fields available on the action.
     *
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        $firstStoryName = '';
        $firstStoryHours = null;
        $firstStoryDescription = '';

        // Get the resource ID from the detail page
        $resourceId = $request->resourceId;

        if ($resourceId) {
            $firstStory = Story::find($resourceId);
            if ($firstStory) {
                $firstStoryName = $firstStory->name ?? '';
                $firstStoryHours = $firstStory->hours ?? null;
                $firstStoryDescription = $firstStory->description ?? '';
            }
        }

        return [
            Text::make(__('Tag Name'), 'tag_name')
                ->default($firstStoryName)
                ->help(__('Modify the tag name if needed'))
                ->nullable(),

            MarkdownTui::make(__('Description'), 'description')
                ->hideFromIndex()
                ->default($this->convertHtmlToMarkdown($firstStoryDescription))
                ->help(__('Modify the description if needed.'))
                ->initialEditType(EditorType::MARKDOWN)
                ->nullable(),

            Number::make(__('Estimate (hours)'), 'estimate')
                ->default($firstStoryHours)
                ->help(__('Modify the estimate (hours) if needed'))
                ->step(0.01),
        ];
    }

    /**
     * Get the displayable name of the action.
     *
     * @return string
     */
    public function name()
    {
        return __('Convert Ticket to Tag');
    }

    /**
     * Check if the content contains HTML tags
     *
     * @param  string  $content
     * @return bool
     */
    private function containsHtml($content)
    {
        return $content !== strip_tags($content);
    }

    /**
     * Convert HTML content to Markdown
     *
     * @param  string  $html
     * @return string
     */
    private function convertHtmlToMarkdown($html)
    {
        // Remove HTML comments
        $html = preg_replace('/<!--.*?-->/s', '', $html);

        // Convert common HTML tags to Markdown
        $html = preg_replace_callback('/<h([1-6])>(.*?)<\/h[1-6]>/i', function ($matches) {
            return str_repeat('#', (int) $matches[1]).' '.$matches[2]."\n\n";
        }, $html);
        $html = preg_replace('/<p>(.*?)<\/p>/i', '$1'."\n\n", $html);
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html);
        $html = preg_replace('/<strong>(.*?)<\/strong>/i', '**$1**', $html);
        $html = preg_replace('/<b>(.*?)<\/b>/i', '**$1**', $html);
        $html = preg_replace('/<em>(.*?)<\/em>/i', '*$1*', $html);
        $html = preg_replace('/<i>(.*?)<\/i>/i', '*$1*', $html);
        $html = preg_replace('/<code>(.*?)<\/code>/i', '`$1`', $html);
        $html = preg_replace('/<pre><code>(.*?)<\/code><\/pre>/is', "```\n$1\n```\n\n", $html);
        $html = preg_replace('/<ul>(.*?)<\/ul>/is', '$1', $html);
        $html = preg_replace('/<ol>(.*?)<\/ol>/is', '$1', $html);
        $html = preg_replace('/<li>(.*?)<\/li>/i', '- $1'."\n", $html);
        $html = preg_replace('/<a\s+href=["\']([^"\']*)["\'][^>]*>(.*?)<\/a>/i', '[$2]($1)', $html);
        $html = preg_replace('/<img[^>]+src=["\']([^"\']*)["\'][^>]*alt=["\']([^"\']*)["\'][^>]*>/i', '![$2]($1)', $html);
        $html = preg_replace('/<img[^>]+src=["\']([^"\']*)["\'][^>]*>/i', '![]($1)', $html);
        $html = preg_replace('/<blockquote>(.*?)<\/blockquote>/is', '> $1'."\n\n", $html);
        $html = preg_replace('/<hr\s*\/?>/i', '---'."\n\n", $html);

        // Remove remaining HTML tags
        $html = strip_tags($html);

        // Clean up extra whitespace and newlines
        $html = preg_replace('/\n\s*\n\s*\n/', "\n\n", $html);
        $html = trim($html);

        return $html;
    }
}

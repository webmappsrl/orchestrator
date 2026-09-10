<?php

namespace App\Console\Commands;

use App\Models\Story;
use App\Models\Tag;
use App\Models\TagGroup;
use Illuminate\Console\Command;

class CompareTagsSalRollup extends Command
{
    protected $signature = 'tags:compare-sal-rollup {--only-changed : mostra solo i tag con uno scostamento}';

    protected $description = 'Confronta il SAL attuale (solo story taggate) col nuovo SAL (story taggate + figli), read-only.';

    public function handle(): int
    {
        // TagGroup e' un modello Eloquent separato su una tabella diversa (tag_groups):
        // Tag::all() non la include mai, nessun filtro su taggable_type necessario per escluderla.
        $this->info('Tag:');
        $this->compare(Tag::all());

        $this->newLine();
        $this->info('TagGroup:');
        $this->compare(TagGroup::all());

        return self::SUCCESS;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Tag>  $tags
     */
    private function compare($tags): void
    {
        $rows = [];

        foreach ($tags as $tag) {
            $taggedIds = $tag->tagged()->pluck('stories.id');
            $before = $taggedIds->isEmpty() ? null : round($tag->tagged()->sum('hours'), 2);
            $after = $tag->getTotalHoursAttribute();

            $delta = ($before !== null && $after !== null) ? round($after - $before, 2) : null;

            // $delta === null significa "dati assenti" (tag senza story taggate), non "nessuno
            // scostamento": va sempre mostrato, --only-changed filtra solo gli scostamenti reali a 0.
            if ($this->option('only-changed') && $delta !== null && (float) $delta === 0.0) {
                continue;
            }

            $hasNegativeChild = Story::whereIn('id', Story::idsWithChildren($taggedIds))
                ->where('hours', '<', 0)
                ->exists();

            $rows[] = [
                $tag->id,
                $tag->name,
                $before ?? '—',
                $after ?? '—',
                $delta === null ? '—' : ($delta > 0 ? "+{$delta}" : $delta),
                $hasNegativeChild ? '⚠️ hours negative' : '',
            ];
        }

        $this->table(['ID', 'Nome', 'SAL attuale', 'SAL nuovo', 'Delta', 'Note'], $rows);
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Story;
use App\Models\Tag;
use App\Models\User;
use App\Enums\StoryStatus;
use App\Enums\StoryType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ScrumArchiveCommand extends Command
{
    protected $signature = 'orchestrator:scrum-archive';
    protected $description = 'Archive all scrum tickets created before today, setting status to DONE, updating creator and tag';

    public function handle()
    {
        // Verifica configurazione
        $creatorId = env('SCRUM_ARCHIVE_CREATOR_ID');
        $tagId = env('SCRUM_ARCHIVE_TAG_ID');

        if (empty($creatorId) || empty($tagId)) {
            $this->error('Configuration error: SCRUM_ARCHIVE_CREATOR_ID and SCRUM_ARCHIVE_TAG_ID must be set in .env');
            return Command::FAILURE;
        }

        // Verifica che creator e tag esistano
        $creator = User::find($creatorId);
        if (!$creator) {
            $this->error("Creator with ID {$creatorId} not found");
            return Command::FAILURE;
        }

        $tag = Tag::find($tagId);
        if (!$tag) {
            $this->error("Tag with ID {$tagId} not found");
            return Command::FAILURE;
        }

        // Filtra ticket Scrum creati fino a ieri (escluso oggi)
        $stories = Story::where('type', StoryType::Scrum->value)
            ->whereDate('created_at', '<', Carbon::today())
            ->get();

        if ($stories->isEmpty()) {
            $this->info('No scrum stories found to archive.');
            return Command::SUCCESS;
        }

        $this->info("Found {$stories->count()} scrum story/stories to archive.");

        $successCount = 0;
        $errorCount = 0;

        foreach ($stories as $story) {
            try {
                // Aggiorna status e creator_id
                $story->status = StoryStatus::Done->value;
                $story->creator_id = $creatorId;

                // Rimuove tutti i tag esistenti e associa solo il tag configurato
                $story->tags()->sync([$tagId]);

                // Salva senza trigger degli observer
                $story->saveQuietly();

                $this->info("Story ID {$story->id} archived successfully.");
                $successCount++;
            } catch (\Exception $e) {
                $this->error("Error processing Story ID {$story->id}: " . $e->getMessage());
                Log::error("ScrumArchiveCommand: Error processing Story ID {$story->id}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $errorCount++;
            }
        }

        $this->info("Archive completed. Success: {$successCount}, Errors: {$errorCount}.");

        return Command::SUCCESS;
    }
}


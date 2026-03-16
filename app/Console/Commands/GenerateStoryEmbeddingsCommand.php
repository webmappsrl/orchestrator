<?php

namespace App\Console\Commands;

use App\Models\Story;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateStoryEmbeddingsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stories:generate-embeddings 
                            {--limit= : Numero massimo di storie da processare}
                            {--force : Rigenera embeddings anche se già presenti}
                            {--chunk=100 : Numero di storie da processare per batch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Genera embeddings per le storie esistenti nel database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Inizio generazione embeddings per le storie...');

        $query = Story::query();

        // Se --force non è specificato, processa solo storie senza embedding
        if (!$this->option('force')) {
            $query->whereNull('embedding');
        }

        $totalStories = $query->count();

        if ($totalStories === 0) {
            $this->info('Nessuna storia da processare.');
            return Command::SUCCESS;
        }

        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $chunkSize = (int) $this->option('chunk');
        $processed = 0;
        $successful = 0;
        $failed = 0;

        $this->info("Trovate {$totalStories} storie da processare.");

        if ($limit) {
            $query->limit($limit);
            $this->info("Limite impostato a {$limit} storie.");
        }

        $bar = $this->output->createProgressBar($limit ?? $totalStories);
        $bar->start();

        $query->chunk($chunkSize, function ($stories) use (&$processed, &$successful, &$failed, $bar) {
            foreach ($stories as $story) {
                try {
                    if ($story->generateEmbedding()) {
                        $successful++;
                    } else {
                        $failed++;
                        Log::warning("Impossibile generare embedding per Story {$story->id}");
                    }
                } catch (\Exception $e) {
                    $failed++;
                    Log::error("Errore nella generazione embedding per Story {$story->id}", [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }

                $processed++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info("Processamento completato!");
        $this->table(
            ['Metrica', 'Valore'],
            [
                ['Storie processate', $processed],
                ['Embeddings generati con successo', $successful],
                ['Errori', $failed],
            ]
        );

        return Command::SUCCESS;
    }
}

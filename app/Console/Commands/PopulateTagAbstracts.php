<?php

namespace App\Console\Commands;

use App\Models\Tag;
use Illuminate\Console\Command;

class PopulateTagAbstracts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tags:populate-abstracts {--force : Forza l\'aggiornamento anche se l\'abstract esiste già}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Popola il campo abstract per tutti i tag basandosi sulle sigle';

    /**
     * Mappatura delle sigle ai loro significati
     */
    private $acronyms = [
        'SOAD' => 'Servizi di ordinaria amministrazione a distanza',
        'SOAP' => 'Servizio di ordinaria amministrazione in presenza',
        'MS' => 'Montagna Servizi',
        'FR' => 'Fund Raising',
        'FS' => 'Fund Raising', // FS è un alias di FR
        'SOAC' => 'Servizi di ordinaria amministrazione per consulenze specialistiche',
        'SOA' => 'Servizi di ordinaria amministrazione', // Fallback per SOA
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Popolando gli abstract dei tag...');

        $tags = Tag::all();
        $updated = 0;
        $skipped = 0;

        foreach ($tags as $tag) {
            // Se il tag ha già un abstract e non è forzato, salta
            if (!empty($tag->abstract) && !$this->option('force')) {
                $skipped++;
                continue;
            }

            // Estrai la sigla dal nome del tag
            $abstract = $this->extractAbstract($tag->name);

            if ($abstract) {
                $tag->abstract = $abstract;
                $tag->save();
                $updated++;
                $this->line("  ✓ Tag ID {$tag->id} ({$tag->name}): {$abstract}");
            } else {
                $skipped++;
                $this->warn("  ✗ Tag ID {$tag->id} ({$tag->name}): nessuna sigla riconosciuta");
            }
        }

        $this->info("\n✅ Completato!");
        $this->info("  - Aggiornati: {$updated}");
        $this->info("  - Saltati: {$skipped}");
        $this->info("  - Totale: " . $tags->count());
    }

    /**
     * Estrae l'abstract dal nome del tag basandosi sulle sigle
     *
     * @param string $tagName
     * @return string|null
     */
    private function extractAbstract(string $tagName): ?string
    {
        // Cerca le sigle all'inizio del nome (prima dello slash o spazio)
        foreach ($this->acronyms as $acronym => $meaning) {
            // Pattern: sigla seguita da / o spazio o fine stringa
            if (preg_match('/^' . preg_quote($acronym, '/') . '(\/|\s)(.+)$/i', $tagName, $matches)) {
                // Estrai la parte specifica dopo lo slash
                $specificPart = trim($matches[2]);
                // Combina il significato generico con quello specifico
                return $meaning . ' - ' . $specificPart;
            } elseif (preg_match('/^' . preg_quote($acronym, '/') . '(\/|\s|$)/i', $tagName)) {
                // Se non c'è parte specifica, usa solo il significato generico
                return $meaning;
            }
        }

        return null;
    }
}

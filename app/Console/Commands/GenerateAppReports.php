<?php

namespace App\Console\Commands;

use App\Jobs\GenerateAppReportJob;
use App\Models\App;
use Illuminate\Console\Command;

/**
 * Pre-genera i report PDF di tutte le app pubblicate sugli store (oc:8242):
 * schedulato di notte, così il click su "Store report" serve il file già
 * pronto invece di far aspettare la generazione (~30s per app).
 */
class GenerateAppReports extends Command
{
    protected $signature = 'apps:generate-reports {--fresh : Rigenera anche i PDF già esistenti del mese corrente}';

    protected $description = 'Accoda la generazione dei report PDF per tutte le app attive pubblicate sugli store';

    public function handle(): int
    {
        $apps = App::active()->get()->filter(fn (App $app) => $app->hasStorePresence());

        $queued = 0;
        $skipped = 0;

        foreach ($apps as $app) {
            $path = $app->reportPdfPath();

            if (! $this->option('fresh') && file_exists($path)) {
                $skipped++;

                continue;
            }

            GenerateAppReportJob::dispatch($app->id, $app->name, $app->storeBundleId(), $path)
                ->onQueue('reports');
            $queued++;
        }

        $this->info("{$queued} report accodati, {$skipped} già presenti (usa --fresh per rigenerarli).");

        return self::SUCCESS;
    }
}

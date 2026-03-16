<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class RefreshReportsCacheCommand extends Command
{
    protected $signature   = 'reports:refresh-cache';
    protected $description = 'Esegue genera_report.py per aggiornare la cache globale dei download app (App Store + Google Play)';

    public function handle(): int
    {
        $wmReportsDir = base_path('wm-reports');
        $pythonBin    = env('PYTHON_REPORTS_BIN', 'python3');
        $script       = $wmReportsDir . '/genera_report.py';

        if (!file_exists($script)) {
            $this->error("Script non trovato: {$script}");
            return self::FAILURE;
        }

        $this->info('Avvio genera_report.py...');

        $process = new Process([$pythonBin, $script], $wmReportsDir);
        $process->setTimeout(1800); // 30 min max

        $process->run(function ($type, $buffer) {
            $this->line(trim($buffer));
        });

        if (!$process->isSuccessful()) {
            $this->error('genera_report.py terminato con errore:');
            $this->line($process->getErrorOutput());
            return self::FAILURE;
        }

        $this->info('Cache aggiornata con successo.');
        return self::SUCCESS;
    }
}

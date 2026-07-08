<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class GenerateAppReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;

    protected $appId;
    protected $appName;
    protected $bundleId;
    protected $outputPath;

    public function __construct(int $appId, string $appName, ?string $bundleId, string $outputPath)
    {
        $this->appId      = $appId;
        $this->appName    = $appName;
        $this->bundleId   = $bundleId;
        $this->outputPath = $outputPath;
    }

    public function getAppId(): int
    {
        return $this->appId;
    }

    public function handle(): void
    {
        $wmReportsDir = base_path('wm-reports');
        $pythonBin    = env('PYTHON_REPORTS_BIN', 'python3');

        $outputDir = dirname($this->outputPath);
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $cmd = [$pythonBin, 'genera_report_app.py',
            '--app',    $this->appName,
            '--output', $this->outputPath,
        ];

        // Senza bundle lo script fa fuzzy match sul nome negli store:
        // meglio di un bundle sbagliato che azzera i dati (oc:8242).
        if (!empty($this->bundleId)) {
            array_splice($cmd, 4, 0, ['--bundle-id', $this->bundleId]);
        }

        $process = new Process($cmd, $wmReportsDir);
        $process->setTimeout(290);
        $process->run();

        if (!$process->isSuccessful()) {
            Log::error('GenerateAppReportJob failed', [
                'app_id'    => $this->appId,
                'bundle_id' => $this->bundleId,
                'stderr'    => $process->getErrorOutput(),
                'stdout'    => $process->getOutput(),
            ]);
            throw new \RuntimeException(
                "Report generation failed for [{$this->bundleId}]: " . $process->getErrorOutput()
            );
        }

        if (!file_exists($this->outputPath)) {
            $stdout = trim($process->getOutput());
            Log::error('GenerateAppReportJob: PDF not created', [
                'app_id'    => $this->appId,
                'bundle_id' => $this->bundleId,
                'stdout'    => $stdout,
            ]);
            throw new \RuntimeException(
                "PDF not created for [{$this->bundleId}]. Output: " . $stdout
            );
        }

        Log::info('GenerateAppReportJob completed', [
            'app_id' => $this->appId,
            'output' => $this->outputPath,
        ]);
    }
}

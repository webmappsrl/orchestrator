<?php

namespace App\Jobs;

use App\Mail\PerformanceReportReady;
use App\Models\User;
use App\Services\Metrics\StoryMetricsCalculator;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class GeneratePerformanceReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $queue = 'reports';
    public int $tries = 3;

    public function __construct(
        public readonly int $userId,
        public readonly int $year,
        public readonly int $quarter,
        public readonly ?int $requestedByUserId = null,
    ) {}

    public function handle(StoryMetricsCalculator $calc): void
    {
        $developer   = User::findOrFail($this->userId);
        $metrics     = $calc->developerMetrics($this->userId, $this->year, $this->quarter);
        $teamAvg     = $calc->teamAverages($this->year, $this->quarter);
        $generatedAt = Carbon::now()->format('d/m/Y H:i');

        $pdf = Pdf::loadView('pdf.developer-performance-report', [
            'developer'   => $developer,
            'metrics'     => $metrics,
            'teamAverages'=> $teamAvg,
            'year'        => $this->year,
            'quarter'     => $this->quarter,
            'generatedAt' => $generatedAt,
        ]);

        $filename = "performance-reports/Q{$this->quarter}-{$this->year}-{$developer->id}.pdf";
        Storage::disk('public')->put($filename, $pdf->output());

        $pdfUrl = Storage::disk('public')->url($filename);

        Log::info("GeneratePerformanceReportJob: report generato", [
            'user_id'               => $this->userId,
            'year'                  => $this->year,
            'quarter'               => $this->quarter,
            'filename'              => $filename,
            'url'                   => $pdfUrl,
            'requested_by_user_id'  => $this->requestedByUserId,
        ]);

        if ($this->requestedByUserId) {
            $requester = User::find($this->requestedByUserId);
            if ($requester) {
                Mail::to($requester->email)->send(
                    new PerformanceReportReady($developer, $this->year, $this->quarter, $pdfUrl)
                );
            }
        }
    }
}

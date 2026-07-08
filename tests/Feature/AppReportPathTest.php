<?php

namespace Tests\Feature;

use App\Http\Controllers\AppReportController;
use App\Models\App;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use ReflectionMethod;
use Tests\TestCase;

class AppReportPathTest extends TestCase
{
    use DatabaseTransactions;

    private function pdfPath(App $app): string
    {
        $method = new ReflectionMethod(AppReportController::class, 'pdfPath');
        $method->setAccessible(true);

        return $method->invoke(new AppReportController(), $app);
    }

    public function test_pdf_path_is_shard_qualified(): void
    {
        $geohub = App::factory()->create(['shard' => 'geohub', 'app_id' => '910001', 'name' => 'Cammini']);
        $maphub = App::factory()->create(['shard' => 'maphub', 'app_id' => '910001', 'name' => 'Cammini']);

        $month = now()->format('Y-m');

        $this->assertStringEndsWith("webmapp_report_app_geohub_Cammini_{$month}.pdf", $this->pdfPath($geohub));
        $this->assertStringEndsWith("webmapp_report_app_maphub_Cammini_{$month}.pdf", $this->pdfPath($maphub));
        $this->assertNotSame($this->pdfPath($geohub), $this->pdfPath($maphub));
    }
}

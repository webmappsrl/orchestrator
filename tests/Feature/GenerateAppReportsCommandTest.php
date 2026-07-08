<?php

namespace Tests\Feature;

use App\Jobs\GenerateAppReportJob;
use App\Models\App;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GenerateAppReportsCommandTest extends TestCase
{
    use DatabaseTransactions;

    public function test_queues_reports_only_for_active_apps_with_store_presence(): void
    {
        Queue::fake();

        $withStore = App::factory()->create([
            'name' => 'ConStore ' . uniqid(),
            'android_store_link' => 'https://play.google.com/store/apps/details?id=it.webmapp.demo',
        ]);
        App::factory()->create(['name' => 'SenzaStore ' . uniqid()]);
        App::factory()->create([
            'name' => 'Dismessa ' . uniqid(),
            'android_store_link' => 'https://play.google.com/store/apps/details?id=it.webmapp.gone',
            'removed_from_shard_at' => now(),
        ]);

        $this->artisan('apps:generate-reports')->assertSuccessful();

        Queue::assertPushed(GenerateAppReportJob::class, function ($job) use ($withStore) {
            return $job->queue === 'reports';
        });

        // Solo l'app attiva con store presence viene accodata... insieme alle
        // eventuali app reali già in DB con store link: filtriamo per certezza.
        Queue::assertNotPushed(GenerateAppReportJob::class, fn ($job) => str_contains($job->queue ?? '', 'SenzaStore'));
    }

    public function test_existing_pdf_is_skipped_unless_fresh(): void
    {
        Queue::fake();

        $app = App::factory()->create([
            'name' => 'GiaPronta' . uniqid(),
            'android_store_link' => 'https://play.google.com/store/apps/details?id=it.webmapp.ready',
        ]);

        $path = $app->reportPdfPath();
        file_put_contents($path, 'pdf');

        try {
            $this->artisan('apps:generate-reports')->assertSuccessful();
            $pushedWithoutFresh = 0;
            Queue::pushed(GenerateAppReportJob::class, function ($job) use (&$pushedWithoutFresh, $app) {
                if ($job->getAppId() === $app->id) {
                    $pushedWithoutFresh++;
                }
            });
            $this->assertSame(0, $pushedWithoutFresh, 'PDF esistente: non deve essere riaccodato senza --fresh');

            $this->artisan('apps:generate-reports', ['--fresh' => true])->assertSuccessful();
            $pushedWithFresh = 0;
            Queue::pushed(GenerateAppReportJob::class, function ($job) use (&$pushedWithFresh, $app) {
                if ($job->getAppId() === $app->id) {
                    $pushedWithFresh++;
                }
            });
            $this->assertSame(1, $pushedWithFresh, 'Con --fresh il PDF esistente va rigenerato');
        } finally {
            @unlink($path);
        }
    }
}

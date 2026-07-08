<?php

namespace Tests\Feature;

use App\Http\Controllers\AppReportController;
use App\Models\App;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use ReflectionMethod;
use Tests\TestCase;

class AppReportBundleIdTest extends TestCase
{
    use DatabaseTransactions;

    private function bundleId(App $app): ?string
    {
        $method = new ReflectionMethod(AppReportController::class, 'bundleId');
        $method->setAccessible(true);

        return $method->invoke(new AppReportController(), $app);
    }

    public function test_bundle_comes_from_the_play_store_link_when_present(): void
    {
        $app = App::factory()->create([
            'app_id' => '17',
            'android_store_link' => 'https://play.google.com/store/apps/details?id=it.webmapp.ucvs',
        ]);

        $this->assertSame('it.webmapp.ucvs', $this->bundleId($app));
    }

    public function test_bundle_falls_back_to_app_id_only_if_it_is_a_real_bundle(): void
    {
        $bundleApp = App::factory()->create(['app_id' => 'it.webmapp.demo']);
        $this->assertSame('it.webmapp.demo', $this->bundleId($bundleApp));
    }

    public function test_numeric_app_id_yields_null_so_the_script_matches_by_name(): void
    {
        $numericApp = App::factory()->create(['app_id' => '53']);
        $this->assertNull($this->bundleId($numericApp));
    }

    public function test_store_presence_detection(): void
    {
        $withLink = App::factory()->create(['app_id' => '17', 'android_store_link' => 'https://play.google.com/store/apps/details?id=it.webmapp.ucvs']);
        $withBundle = App::factory()->create(['app_id' => 'it.webmapp.demo']);
        $without = App::factory()->create(['app_id' => '53']);

        $this->assertTrue($withLink->hasStorePresence());
        $this->assertTrue($withBundle->hasStorePresence());
        $this->assertFalse($without->hasStorePresence());
    }
}

<?php

namespace Tests\Feature;

use App\Models\Quote;
use App\Services\MediaLibrary\OrchestratorPathGenerator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrchestratorPathGeneratorTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config(['media-library.disk_name' => 'public']);
    }

    public function test_new_upload_uses_layout_c(): void
    {
        $quote = Quote::factory()->create();
        $file = UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf');
        $media = $quote->addMedia($file)->toMediaCollection('documents');

        $generator = new OrchestratorPathGenerator();
        $path = $generator->getPath($media);

        $this->assertStringContainsString('orchestrator/media/' . $media->id, $path);
    }

    public function test_falls_back_to_layout_b_when_file_exists_there(): void
    {
        $quote = Quote::factory()->create();
        $file = UploadedFile::fake()->create('legacy_b.pdf', 10, 'application/pdf');
        $media = $quote->addMedia($file)->toMediaCollection('documents');

        // addMedia() scrive in Layout C — lo rimuoviamo per simulare un file legacy
        Storage::disk('public')->delete('orchestrator/media/' . $media->id . '/' . $media->file_name);

        $modelName = $quote->name ?? (string) $quote->id;
        Storage::disk('public')->put('media/Quote/' . $modelName . '/' . $media->id . '/' . $media->file_name, 'content');

        $generator = new OrchestratorPathGenerator();
        $path = $generator->getPath($media);

        $this->assertStringContainsString('media/Quote/' . $modelName . '/' . $media->id, $path);
    }

    public function test_falls_back_to_layout_a_when_file_exists_there(): void
    {
        $quote = Quote::factory()->create();
        $file = UploadedFile::fake()->create('legacy_a.pdf', 10, 'application/pdf');
        $media = $quote->addMedia($file)->toMediaCollection('documents');

        // addMedia() scrive in Layout C — lo rimuoviamo per simulare un file legacy
        Storage::disk('public')->delete('orchestrator/media/' . $media->id . '/' . $media->file_name);

        $modelName = $quote->name ?? (string) $quote->id;
        Storage::disk('public')->put('media/Quote/' . $modelName . '/' . $media->file_name, 'content');

        $generator = new OrchestratorPathGenerator();
        $path = $generator->getPath($media);

        $this->assertEquals('media/Quote/' . $modelName . '/', $path);
    }

    public function test_layout_c_takes_priority_over_b_and_a(): void
    {
        $quote = Quote::factory()->create();
        $file = UploadedFile::fake()->create('priority.pdf', 10, 'application/pdf');
        $media = $quote->addMedia($file)->toMediaCollection('documents');

        $modelName = $quote->name ?? (string) $quote->id;
        Storage::disk('public')->put('orchestrator/media/' . $media->id . '/' . $media->file_name, 'c');
        Storage::disk('public')->put('media/Quote/' . $modelName . '/' . $media->id . '/' . $media->file_name, 'b');
        Storage::disk('public')->put('media/Quote/' . $modelName . '/' . $media->file_name, 'a');

        $generator = new OrchestratorPathGenerator();
        $path = $generator->getPath($media);

        $this->assertStringContainsString('orchestrator/media/' . $media->id, $path);
    }

    public function test_app_service_provider_restores_path_generator_and_disk(): void
    {
        config([
            'media-library.path_generator' => \Wm\WmPackage\Support\PathGenerator\WmfePathGenerator::class,
            'media-library.disk_name' => 'wmfe',
        ]);

        (new \App\Providers\AppServiceProvider(app()))->register();

        $this->assertEquals(
            \App\Services\MediaLibrary\OrchestratorPathGenerator::class,
            config('media-library.path_generator')
        );
        $this->assertEquals('public', config('media-library.disk_name'));
    }
}

<?php

namespace Tests\Feature;

use App\Exports\SelectedStoriesToExcel;
use App\Models\Story;
use App\Models\Tag;
use App\Models\User;
use App\Nova\Actions\ExportStoriesToExcel;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class ExportStoriesToExcelActionTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $this->actingAs($user);
    }

    /** @test */
    public function it_stores_export_with_tag_report_filename_on_public_disk()
    {
        Excel::fake();
        Carbon::setTestNow(Carbon::create(2026, 4, 2, 12, 0, 0));

        $tag = Tag::factory()->create([
            'name' => 'example tag',
        ]);

        $stories = Story::factory()->count(2)->create();
        foreach ($stories as $story) {
            $story->tags()->attach($tag->id);
        }

        // Nova action reads viaResource/viaResourceId from the current request (NovaRequest).
        app()->instance(
            NovaRequest::class,
            NovaRequest::create('/nova-api/stories/action', 'POST', [
                'viaResource' => 'tags',
                'viaResourceId' => (string) $tag->id,
            ])
        );

        $action = new ExportStoriesToExcel();
        $fields = new ActionFields(collect(), collect($stories));

        $response = $action->handle($fields, $stories);

        $payload = $response->jsonSerialize();
        $this->assertArrayHasKey('name', $payload);
        $this->assertArrayHasKey('download', $payload);

        $safeTagName = trim((string) preg_replace('/[^\pL\pN]+/u', '_', $tag->name), '_');
        $expectedFileName = "TagReport_{$safeTagName}_20260402.xls";
        $this->assertSame($expectedFileName, $payload['name']);

        Excel::assertStored($payload['name'], 'public', function ($export) use ($stories) {
            return $export instanceof SelectedStoriesToExcel
                && $export->collection()->count() === $stories->count();
        });
        $this->assertStringContainsString($expectedFileName, $payload['download']);
    }

    /** @test */
    public function it_allows_downloading_generated_xls_file()
    {
        $this->withoutMiddleware(); // route is protected by 'nova' middleware

        $fileName = 'TagReport_Test_20260402.xls';
        Storage::disk('public')->put($fileName, 'dummy');

        $res = $this->get(route('stories.excel.download', ['fileName' => $fileName]));

        $res->assertOk();
        $res->assertHeader('content-disposition');
    }

    /** @test */
    public function it_blocks_invalid_download_file_names()
    {
        $this->withoutMiddleware(); // route is protected by 'nova' middleware

        $this->get(route('stories.excel.download', ['fileName' => '../.env']))
            ->assertNotFound();

        $this->get(route('stories.excel.download', ['fileName' => 'evil.php']))
            ->assertNotFound();

        $this->get(route('stories.excel.download', ['fileName' => 'nested/path.xls']))
            ->assertNotFound();
    }

    /** @test */
    public function it_returns_404_when_export_file_does_not_exist()
    {
        $this->withoutMiddleware(); // route is protected by 'nova' middleware

        Storage::disk('public')->delete('TagReport_Missing_20260402.xls');

        $this->get(route('stories.excel.download', ['fileName' => 'TagReport_Missing_20260402.xls']))
            ->assertNotFound();
    }

    /** @test */
    public function it_uses_translated_headings_based_on_locale()
    {
        $export = new SelectedStoriesToExcel(collect());

        app()->setLocale('it');
        $it = $export->headings();
        $this->assertSame('ID Ticket', $it[0]);
        $this->assertSame('URL Ticket', $it[9]);

        app()->setLocale('en');
        $en = $export->headings();
        $this->assertSame('Ticket ID', $en[0]);
        $this->assertSame('Ticket URL', $en[9]);
    }

    /** @test */
    public function it_builds_ticket_url_using_app_url_config()
    {
        config(['app.url' => 'http://localhost:8099']);

        $story = new \App\Models\Story();
        $story->id = 7525;
        $story->status = 'todo';
        $story->name = 'Test';
        $story->customer_request = '<p>Hello</p>';
        $story->setRelation('tags', collect());
        $story->setRelation('creator', null);
        $story->setRelation('developer', null);
        $story->setRelation('tester', null);
        $story->created_at = now();

        $export = new SelectedStoriesToExcel(collect([$story]));
        $row = $export->map($story);

        $this->assertSame('http://localhost:8099/resources/developer-stories/7525', $row[9]);
    }
}


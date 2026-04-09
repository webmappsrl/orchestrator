<?php

namespace Tests\Feature;

use App\Exports\SelectedStoriesToExcel;
use App\Models\Story;
use App\Models\Tag;
use App\Models\User;
use App\Nova\Actions\ExportStoriesToExcel;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Storage;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class ExportStoriesToExcelActionTest extends TestCase
{
    use DatabaseTransactions;

    private function getCurrentDate(): string
    {
        return now()->format('Ymd');
    }

    private function headerKeys(): array
    {
        return [
            'Ticket ID',
            'Ticket status',
            'Created at',
            'Tags list',
            'Creator',
            'Assigned to',
            'Tester',
            'Ticket title',
            'Request',
            'Ticket URL',
        ];
    }

    private function safeTagName(string $name): string
    {
        return trim((string) preg_replace('/[^\pL\pN]+/u', '_', $name), '_');
    }

    private function makeReportFileName(string $tagName, string $date): string
    {
        return "TagReport_{$this->safeTagName($tagName)}_{$date}.xls";
    }

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
        $date = $this->getCurrentDate();

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

        $expectedFileName = $this->makeReportFileName($tag->name, $date);
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

        $date = $this->getCurrentDate();
        $fileName = $this->makeReportFileName('Test', $date);
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

        $date = $this->getCurrentDate();
        $missing = $this->makeReportFileName('Missing', $date);
        Storage::disk('public')->delete($missing);

        $this->get(route('stories.excel.download', ['fileName' => $missing]))
            ->assertNotFound();
    }

    /** @test */
    public function it_uses_translated_headings_based_on_locale()
    {
        $export = new SelectedStoriesToExcel(collect());

        app()->setLocale('it');
        $it = $export->headings();
        $this->assertSame(array_map('__', $this->headerKeys()), $it);

        app()->setLocale('en');
        $en = $export->headings();
        $this->assertSame(array_map('__', $this->headerKeys()), $en);
    }

    /** @test */
    public function it_builds_ticket_url_using_app_url_config()
    {
        config(['app.url' => 'https://orchestrator.dev.maphub.it/']);
        $baseUrl = rtrim((string) config('app.url'), '/');


        $story = Story::factory()->create();
        $export = new SelectedStoriesToExcel(collect([$story]));
        $row = $export->map($story);

        $this->assertSame($baseUrl.'/resources/developer-stories/'.$story->id, $row[9]);
    }

    /** @test */
    public function it_has_translations_for_all_export_header_fields_in_it_and_en()
    {
        $keys = $this->headerKeys();

        foreach (['it', 'en'] as $locale) {
            app()->setLocale($locale);

            foreach ($keys as $key) {
                $this->assertTrue(Lang::has($key, $locale), "Missing translation key '{$key}' for locale '{$locale}'.");
                $translated = __($key);
                $this->assertNotSame('', trim((string) $translated), "Translation for key '{$key}' in locale '{$locale}' is empty.");
            }
        }
    }
}

<?php

namespace Tests\Feature;

use App\Models\Tag;
use App\Models\Story;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Nova\Http\Requests\NovaRequest;
use Tests\TestCase;

class TagSalTest extends TestCase
{
    use DatabaseTransactions;

    public function test_sal_shows_empty_when_no_total_and_no_estimate()
    {
        $empty = __('Empty');
        // Creo un tag con estimate = null
        $tag = Tag::factory()->create(['estimate' => null,]);

        // Assicurati che non ci siano ticket collegati a questo tag
        $totalHours = $tag->getTotalHoursAttribute();
        $this->assertTrue($totalHours === null || $totalHours === 0.0, "Total hours deve essere null o 0.0");

        $tagRequest = NovaRequest::create('/nova-api/tags', 'GET');
        // Istanzio la risorsa Nova associata al modello Tag
        $tagResource = new \App\Nova\Tag($tag);
        // Recupero tutti i campi visibili per questa richiesta
        $tagFields = collect($tagResource->fields($tagRequest));
        // Cerco il campo 'Sal' tra i campi visibili
        $salField = $tagFields->firstWhere('name', 'Sal');
        $this->assertNotNull($salField, 'Campo Sal non trovato');

        $salField->resolve($tag);
        $salHtml = $salField->value;
        $this->assertIsString($salHtml, 'Il campo Sal non restituisce una stringa');

        $this->assertStringContainsString(
            "<a style=\"font-weight:bold;\"> {$empty} </a>",
            $salHtml
        );
    }

    public function test_sal_shows_total_hours_with_empty_estimate()
    {
        $empty = __('Empty');
        $tag = Tag::factory()->create(['estimate' => null]);

        $story1 = Story::factory()->create(['hours' => 15.00]);
        $story2 = Story::factory()->create(['hours' => 20.87]);

        $story1->tags()->attach($tag->id);
        $story2->tags()->attach($tag->id);

        $tagRequest = NovaRequest::create('/nova-api/tags', 'GET');

        $tagResource = new \App\Nova\Tag($tag);
        $tagFields = collect($tagResource->fields($tagRequest));

        $salField = $tagFields->firstWhere('name', 'Sal');
        $this->assertNotNull($salField, 'Campo Sal non trovato');

        $salField->resolve($tag);
        $salHtml = $salField->value;
        $this->assertIsString($salHtml, 'Il campo Sal non restituisce una stringa');

        $this->assertStringContainsString(
            "<a style=\"font-weight:bold;\"> 35.87 / {$empty} </a>",
            $salHtml
        );
    }

    public function test_sal_shows_estimate_with_empty_total()
    {
        $empty = __('Empty');
        $tag = Tag::factory()->create(['estimate' => 32]);

        $tagRequest = NovaRequest::create('/nova-api/tags', 'GET');

        $tagResource = new \App\Nova\Tag($tag);
        $tagFields = collect($tagResource->fields($tagRequest));

        $salField = $tagFields->firstWhere('name', 'Sal');
        $this->assertNotNull($salField, 'Campo Sal non trovato');

        $salField->resolve($tag);
        $salHtml = $salField->value;
        $this->assertIsString($salHtml, 'Il campo Sal non restituisce una stringa');

        $this->assertStringContainsString(
            "<a style=\"font-weight:bold;\"> {$empty} / 32 </a>",
            $salHtml
        );
    }

    public function test_sal_shows_total_and_estimate_with_percentage()
    {
        $tag = Tag::factory()->create(['estimate' => 48]);

        $story1 = Story::factory()->create(['hours' => 20.75]);
        $story2 = Story::factory()->create(['hours' => 20.00]);

        $story1->tags()->attach($tag->id);
        $story2->tags()->attach($tag->id);

        $tagRequest = NovaRequest::create('/nova-api/tags', 'GET');
        $tagResource = new \App\Nova\Tag($tag);
        $tagFields = collect($tagResource->fields($tagRequest));

        $salField = $tagFields->firstWhere('name', 'Sal');
        $this->assertNotNull($salField, 'Campo Sal non trovato');

        $salField->resolve($tag);
        $salHtml = $salField->value;
        $this->assertIsString($salHtml, 'Il campo Sal non restituisce una stringa');

        $this->assertStringContainsString('😊', $salHtml);
        $this->assertStringContainsString('<a style="color:orange; font-weight:bold;"> 40.75 / 48 </a>', $salHtml);
        $this->assertStringContainsString('<a style="color:orange; font-weight:bold;"> [84.9%] </a>', $salHtml);
    }

    public function test_sal_shows_angry_trend_when_percentage_is_100_or_more()
    {
        $tag = Tag::factory()->create(['estimate' => 20]);

        $story = Story::factory()->create(['hours' => 50]);
        $story->tags()->attach($tag->id);

        $tagRequest = NovaRequest::create('/nova-api/tags', 'GET');
        $tagResource = new \App\Nova\Tag($tag);
        $salField = collect($tagResource->fields($tagRequest))->firstWhere('name', 'Sal');
        $this->assertNotNull($salField, 'Campo Sal non trovato');

        $salField->resolve($tag);
        $salHtml = $salField->value;
        $this->assertIsString($salHtml, 'Il campo Sal non restituisce una stringa');

        $this->assertStringContainsString('😡', $salHtml);
    }

    public function test_sal_shows_smile_trend_when_percentage_is_below_100()
    {
        $tag = Tag::factory()->create(['estimate' => 20]);

        $story = Story::factory()->create(['hours' => 15]);
        $story->tags()->attach($tag->id);

        $tagRequest = NovaRequest::create('/nova-api/tags', 'GET');
        $tagResource = new \App\Nova\Tag($tag);
        $salField = collect($tagResource->fields($tagRequest))->firstWhere('name', 'Sal');
        $this->assertNotNull($salField, 'Campo Sal non trovato');

        $salField->resolve($tag);
        $salHtml = $salField->value;
        $this->assertIsString($salHtml, 'Il campo Sal non restituisce una stringa');

        $this->assertStringContainsString('😊', $salHtml);
    }

    public function test_sal_shows_green_color_when_tag_is_closed()
    {
        $tag = Tag::factory()->create(['estimate' => 20]);

        $story = Story::factory()->create(['hours' => 15, 'status' => 'done']);
        $story->tags()->attach($tag->id);

        // Verifica che il tag sia effettivamente chiuso
        $this->assertTrue($tag->isClosed(), 'Il tag dovrebbe essere chiuso');

        $tagRequest = NovaRequest::create('/nova-api/tags', 'GET');
        $tagResource = new \App\Nova\Tag($tag);
        $salField = collect($tagResource->fields($tagRequest))->firstWhere('name', 'Sal');
        $this->assertNotNull($salField, 'Campo Sal non trovato');

        $salField->resolve($tag);
        $salHtml = $salField->value;
        $this->assertIsString($salHtml, 'Il campo Sal non restituisce una stringa');

        $this->assertStringContainsString('color:green', $salHtml);
    }

    public function test_sal_shows_orange_color_when_tag_is_open()
    {
        $tag = Tag::factory()->create(['estimate' => 20]);

        $story = Story::factory()->create(['hours' => 15, 'status' => 'in_progress']);
        $story->tags()->attach($tag->id);

        $tagRequest = NovaRequest::create('/nova-api/tags', 'GET');
        $tagResource = new \App\Nova\Tag($tag);
        $salField = collect($tagResource->fields($tagRequest))->firstWhere('name', 'Sal');
        $this->assertNotNull($salField, 'Campo Sal non trovato');

        $salField->resolve($tag);
        $salHtml = $salField->value;
        $this->assertIsString($salHtml, 'Il campo Sal non restituisce una stringa');

        $this->assertStringContainsString('color:orange', $salHtml);
    }
}

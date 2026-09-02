<?php

namespace Tests\Feature;

use App\Models\Story;
use App\Models\User;
use App\Nova\Story as NovaStory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Nova;
use Tests\TestCase;

class StoryChildFieldTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Le Resource Nova non sono registrate automaticamente nei test:
        // senza questo, detailFields() solleva ResourceMissingException
        // sui campi BelongsTo prima di arrivare al campo in esame.
        Nova::resourcesIn(app_path('Nova'));
    }

    /** @test */
    public function i_campi_di_dettaglio_si_costruiscono_senza_errori()
    {
        // Regressione oc:8445: searchable()/filterable() non esistono su HasMany
        // e la loro presenza produceva un BadMethodCallException, cioe' un 500
        // sul detail di OGNI ticket.
        $user = User::factory()->create();
        $this->actingAs($user);

        $parent = Story::create(['name' => 'Parent']);
        Story::create(['name' => 'Child', 'parent_id' => $parent->id]);

        $request = NovaRequest::create('/', 'GET');
        $request->setUserResolver(fn () => $user);

        $resource = new NovaStory($parent);
        $fields = $resource->detailFields($request);

        $this->assertNotEmpty($fields);
    }

    /** @test */
    public function il_campo_ticket_correlati_e_un_hasmany()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $parent = Story::create(['name' => 'Parent']);

        $request = NovaRequest::create('/', 'GET');
        $request->setUserResolver(fn () => $user);

        $resource = new NovaStory($parent);

        $found = collect($resource->detailFields($request))
            ->flatten()
            ->first(fn ($field) => ($field->attribute ?? null) === 'childStories');

        $this->assertNotNull($found, 'Il campo childStories non e presente nel detail.');
        $this->assertInstanceOf(\Laravel\Nova\Fields\HasMany::class, $found);
    }
}

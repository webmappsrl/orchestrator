<?php

namespace Tests\Feature;

use App\Models\Story;
use App\Models\Tag;
use App\Models\TagGroup;
use App\Models\User;
use App\Nova\Metrics\StoryTime;
use App\Nova\Story as StoryNovaResource;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Laravel\Nova\Http\Requests\NovaRequest;
use Mockery;
use Tests\TestCase;

class StoryHoursRollupTest extends TestCase
{
    use DatabaseTransactions;

    // --- Story::hoursWithChildren() / idsWithChildren() -------------------------------------

    public function test_story_without_children_returns_own_hours_unchanged(): void
    {
        $story = Story::factory()->create(['hours' => 5.5]);

        $this->assertSame(5.5, $story->hoursWithChildren());
    }

    public function test_story_without_children_and_null_hours_stays_null(): void
    {
        $story = Story::factory()->create(['hours' => null]);

        $this->assertNull($story->hoursWithChildren());
    }

    public function test_parent_hours_sum_children_hours(): void
    {
        $parent = Story::factory()->create(['hours' => 5.0]);
        Story::factory()->create(['hours' => 3.0, 'parent_id' => $parent->id]);
        Story::factory()->create(['hours' => 2.5, 'parent_id' => $parent->id]);

        $this->assertEqualsWithDelta(10.5, $parent->hoursWithChildren(), 0.001);
    }

    public function test_parent_with_null_hours_and_valued_children_is_not_null(): void
    {
        $parent = Story::factory()->create(['hours' => null]);
        Story::factory()->create(['hours' => 3.0, 'parent_id' => $parent->id]);
        Story::factory()->create(['hours' => null, 'parent_id' => $parent->id]);

        $this->assertEqualsWithDelta(3.0, $parent->hoursWithChildren(), 0.001);
    }

    public function test_negative_child_hours_are_not_clamped(): void
    {
        $parent = Story::factory()->create(['hours' => 5.0]);
        Story::factory()->create(['hours' => -0.5, 'parent_id' => $parent->id]);

        $this->assertEqualsWithDelta(4.5, $parent->hoursWithChildren(), 0.001);
    }

    public function test_child_story_does_not_aggregate_upward(): void
    {
        $parent = Story::factory()->create(['hours' => 5.0]);
        $child = Story::factory()->create(['hours' => 3.0, 'parent_id' => $parent->id]);

        $this->assertSame(3.0, $child->hoursWithChildren());
    }

    public function test_ids_with_children_deduplicates_across_overlapping_sets(): void
    {
        $parent = Story::factory()->create();
        $child = Story::factory()->create(['parent_id' => $parent->id]);

        $result = Story::idsWithChildren([$parent->id, $child->id]);

        $this->assertCount(2, $result);
        $this->assertContains($parent->id, $result);
        $this->assertContains($child->id, $result);
    }

    public function test_ids_with_children_returns_empty_collection_for_empty_input(): void
    {
        $this->assertTrue(Story::idsWithChildren([])->isEmpty());
    }

    // --- fieldTrait::effectiveHoursField() ---------------------------------------------------

    public function test_effective_hours_field_text_branch_includes_children(): void
    {
        $parent = Story::factory()->create(['hours' => 5.0]);
        Story::factory()->create(['hours' => 3.0, 'parent_id' => $parent->id]);

        // isResourceIndexRequest() controlla instanceof ResourceIndexRequest, non l'URL:
        // una NovaRequest generica farebbe cadere effectiveHoursField() nel ramo Number (create/update).
        $request = \Laravel\Nova\Http\Requests\ResourceIndexRequest::create('/nova-api/stories', 'GET');
        $resource = new StoryNovaResource($parent);
        $fields = $resource->fieldsInIndex($request);

        // effectiveHoursField() e dentro uno Stack (righe "Assigned/estimated/effective hours"),
        // non un campo di primo livello — bisogna scendere in Stack::$lines per trovarlo.
        $flattened = collect($fields)->flatMap(
            fn ($f) => $f instanceof \Laravel\Nova\Fields\Stack ? $f->lines : [$f]
        );
        $effectiveHoursField = $flattened->first(fn ($f) => $f->attribute === 'hours');
        $this->assertNotNull($effectiveHoursField, 'Effective Hours field not found in fieldsInIndex()');
        $effectiveHoursField->resolve($parent);

        $this->assertStringContainsString('8', (string) $effectiveHoursField->value);
    }

    // --- Nova\Metrics\StoryTime::calculate() -------------------------------------------------

    /**
     * Partial mock su una NovaRequest reale (query param range=ALL, cosi Value::aggregate()
     * salta il filtro per data): solo findModel/findResource sono stubbati, il resto
     * (proprieta magiche come ->range, ->filters) usa il comportamento reale della request.
     */
    private function novaDetailMetricRequest($model): NovaRequest
    {
        $real = NovaRequest::create('/nova-api/x', 'GET', ['range' => 'ALL']);
        $mock = Mockery::mock($real)->makePartial();
        $mock->shouldReceive('findModel')->withAnyArgs()->andReturn($model);
        $mock->shouldReceive('findResource')->withAnyArgs()->andReturn(new StoryNovaResource($model));

        return $mock;
    }

    public function test_story_time_metric_on_story_detail_includes_children(): void
    {
        $parent = Story::factory()->create(['hours' => 5.0]);
        Story::factory()->create(['hours' => 3.0, 'parent_id' => $parent->id]);

        $result = (new StoryTime)->calculate($this->novaDetailMetricRequest($parent));

        $this->assertEqualsWithDelta(8.0, (float) $result->value, 0.001);
    }

    public function test_story_time_metric_on_tag_detail_deduplicates_child_tagged_with_same_tag(): void
    {
        $tag = Tag::factory()->create();
        $parent = Story::factory()->create(['hours' => 5.0]);
        $child = Story::factory()->create(['hours' => 3.0, 'parent_id' => $parent->id]);
        $parent->tags()->attach($tag->id);
        $child->tags()->attach($tag->id);

        $result = (new StoryTime)->calculate($this->novaDetailMetricRequest($tag));

        $this->assertEqualsWithDelta(8.0, (float) $result->value, 0.001);
    }

    // --- Tag::getTotalHoursAttribute() --------------------------------------------------------

    public function test_tag_total_hours_includes_untagged_children(): void
    {
        $tag = Tag::factory()->create();
        $parent = Story::factory()->create(['hours' => 5.0]);
        Story::factory()->create(['hours' => 3.0, 'parent_id' => $parent->id]);
        $parent->tags()->attach($tag->id);

        $this->assertEqualsWithDelta(8.0, $tag->getTotalHoursAttribute(), 0.001);
    }

    public function test_tag_total_hours_does_not_double_count_child_tagged_with_same_tag(): void
    {
        $tag = Tag::factory()->create();
        $parent = Story::factory()->create(['hours' => 5.0]);
        $child = Story::factory()->create(['hours' => 3.0, 'parent_id' => $parent->id]);
        $parent->tags()->attach($tag->id);
        $child->tags()->attach($tag->id);

        $this->assertEqualsWithDelta(8.0, $tag->getTotalHoursAttribute(), 0.001);
    }

    public function test_tag_total_hours_is_memoized_per_instance(): void
    {
        $tag = Tag::factory()->create();
        $story = Story::factory()->create(['hours' => 5.0]);
        $story->tags()->attach($tag->id);

        DB::enableQueryLog();
        DB::flushQueryLog();
        $tag->getTotalHoursAttribute(); // prima chiamata: esegue le query reali
        $firstCallQueries = count(DB::getQueryLog());

        DB::flushQueryLog();
        $tag->getTotalHoursAttribute();
        $tag->getTotalHoursAttribute();
        $tag->getTotalHoursAttribute(); // chiamate successive: devono essere memoizzate
        $subsequentCallsQueries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertGreaterThan(0, $firstCallQueries, 'The first call should hit the database');
        $this->assertSame(0, $subsequentCallsQueries, 'Memoized calls should run zero additional queries');
    }

    public function test_tag_group_inherits_children_rollup_without_own_code(): void
    {
        $group = TagGroup::factory()->create();
        $parent = Story::factory()->create(['hours' => 5.0]);
        Story::factory()->create(['hours' => 3.0, 'parent_id' => $parent->id]);
        $group->stories()->attach($parent->id);

        $this->assertEqualsWithDelta(8.0, $group->getTotalHoursAttribute(), 0.001);
    }

    // --- Story::indexQuery() — regressione trovata in review (Task 5 rimosso) -----------------

    private function requestFor(?User $user): NovaRequest
    {
        return NovaRequest::create('/')->setUserResolver(fn () => $user);
    }

    public function test_index_query_does_not_pollute_columns_for_downstream_pluck(): void
    {
        // Regressione trovata in review formale: una versione precedente aggiungeva un
        // addSelect() permanente a Story::indexQuery() per precaricare children_hours_sum
        // (mitigazione anti-N+1). Rompeva silenziosamente qualsiasi ->pluck() successivo fatto
        // da altri chiamanti sulla stessa query (es. Nova\Filters\TaggableTypeFilter::options()),
        // perche' Laravel non sovrascrive le colonne quando sono gia' impostate
        // (Query\Builder::onceWithColumns() sostituisce $columns solo se era null). La mitigazione
        // e' stata rimossa: non era comunque ereditata dalle risorse Nova realmente usate in
        // produzione (DeveloperStory, CustomerStory, ecc. sovrascrivono indexQuery() senza
        // richiamare parent::indexQuery()). N+1 sull'index resta un rischio accettato (overview
        // §Rischi), non silenziosamente "risolto" da codice che non funzionava.
        $admin = User::factory()->create(['roles' => [\App\Enums\UserRole::Admin]]);
        $story = Story::factory()->create(['name' => 'Regression check']);
        $baseQuery = Story::query()->where('id', $story->id);

        // indexQuery() ritorna null per ruoli non-Customer (comportamento originale,
        // non toccato da questo ticket): Nova stesso usa $query as-is in quel caso.
        $names = (StoryNovaResource::indexQuery($this->requestFor($admin), $baseQuery) ?? $baseQuery)
            ->pluck('name', 'id');

        $this->assertSame(['Regression check'], $names->values()->all());
    }

    // --- Comando artisan tags:compare-sal-rollup (Task 8) -------------------------------------

    public function test_compare_sal_rollup_command_runs_read_only_without_error(): void
    {
        $tag = Tag::factory()->create();
        $parent = Story::factory()->create(['hours' => 5.0]);
        Story::factory()->create(['hours' => -1.0, 'parent_id' => $parent->id]);
        $parent->tags()->attach($tag->id);

        $beforeCount = Story::count();

        $exitCode = Artisan::call('tags:compare-sal-rollup');

        $this->assertSame(0, $exitCode);
        $this->assertSame($beforeCount, Story::count(), 'The command must not write any data');
        $this->assertStringContainsString((string) $tag->id, Artisan::output());
    }

    public function test_compare_sal_rollup_command_includes_project_tags(): void
    {
        // Regressione trovata in review formale: la prima versione filtrava con
        // Tag::whereNull('taggable_type'), pensando (erroneamente) che servisse a escludere
        // TagGroup — che invece e' gia' un modello/tabella separata (tag_groups), mai
        // restituita da una query su Tag. L'effetto reale era escludere ~93% dei tag reali
        // (quelli con taggable_type = Project), cioe' proprio i tag "manuali" che il comando
        // deve misurare secondo l'overview (§"Il buco reale e' solo sui tag manuali").
        $tag = Tag::factory()->create(['taggable_type' => \App\Models\Project::class, 'taggable_id' => 1]);
        $story = Story::factory()->create(['hours' => 5.0]);
        $story->tags()->attach($tag->id);

        Artisan::call('tags:compare-sal-rollup');

        $this->assertStringContainsString((string) $tag->id, Artisan::output());
    }

    // --- Non-regressione ----------------------------------------------------------------------

    public function test_tag_estimate_attribute_is_unchanged_by_rollup(): void
    {
        $tag = Tag::factory()->create();
        $parent = Story::factory()->create(['hours' => 5.0, 'estimated_hours' => 10.0]);
        Story::factory()->create(['hours' => 3.0, 'estimated_hours' => 4.0, 'parent_id' => $parent->id]);
        $parent->tags()->attach($tag->id);

        // Solo la story taggata (il padre) contribuisce alla stima, non il figlio non taggato.
        $this->assertEqualsWithDelta(10.0, $tag->getEstimateAttribute(), 0.001);
    }
}

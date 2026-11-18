<?php

namespace Tests\Feature;

use App\Enums\QuoteStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteActivity;
use App\Models\RecurringProduct;
use App\Models\Task;
use App\Models\User;
use App\Nova\Actions\DuplicateQuote;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Attività delle trattative (oc:8724): cosa viene registrato su trattative,
 * prodotti e task, e cosa no.
 */
class QuoteActivityLogTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['roles' => [UserRole::Admin], 'name' => 'Giuseppe Test']);
        $this->actingAs($this->user);
        // Alcuni test vecchi (QuoteGetTotalPriceTest, QuoteGetTotalRecurringPriceTest) non
        // usano transazioni e lasciano righe in `quote_activities`: si parte da tabella vuota.
        QuoteActivity::query()->delete();
    }

    private function makeQuote(array $overrides = []): Quote
    {
        return Quote::create(array_merge([
            'title' => 'Preventivo Comune di X',
            'customer_id' => Customer::factory()->create()->id,
        ], $overrides));
    }

    private function activities(string $event): \Illuminate\Support\Collection
    {
        return QuoteActivity::where('event', $event)->orderBy('id')->get();
    }

    /** @test */
    public function quote_creation_is_recorded_with_user(): void
    {
        $quote = $this->makeQuote();

        $activity = $this->activities(QuoteActivity::EVENT_QUOTE_CREATED)->sole();
        $this->assertSame($quote->id, $activity->quote_id);
        $this->assertSame('Preventivo Comune di X', $activity->quote_title);
        $this->assertSame($this->user->id, $activity->user_id);
        $this->assertSame('Giuseppe Test', $activity->user_name);
    }

    /** @test */
    public function quote_update_records_each_field_with_old_and_new_value(): void
    {
        $quote = $this->makeQuote(['status' => QuoteStatus::New->value]);

        $quote->update(['status' => QuoteStatus::Presented->value, 'notes' => 'Richiamare lunedì']);

        $changes = $this->activities(QuoteActivity::EVENT_QUOTE_UPDATED)->sole()->changes;
        $this->assertSame(['old' => 'new', 'new' => 'presented'], $changes['status']);
        // Campo tradotto: oggetto per lingua, non stringa JSON.
        $this->assertSame(['it' => 'Richiamare lunedì'], $changes['notes']['new']);
    }

    /** @test */
    public function system_fields_are_not_recorded(): void
    {
        $quote = $this->makeQuote();

        $quote->update(['template' => true]);
        $quote->forceFill(['pdf_links' => [['url' => 'x']]])->save();
        $quote->update(['priority' => 15]);

        $this->assertCount(0, $this->activities(QuoteActivity::EVENT_QUOTE_UPDATED));
    }

    /** @test */
    public function empty_locales_added_by_nova_are_not_recorded_as_changes(): void
    {
        // Trattativa creata via API: nel JSON dei campi tradotti c'è solo `it`.
        $quote = $this->makeQuote(['status' => QuoteStatus::New->value, 'notes' => 'Richiamare lunedì']);

        // Come NovaTabTranslatable: salva tutte le lingue, quelle vuote come null.
        foreach (['en', 'fr', 'es', 'de'] as $locale) {
            $quote->setTranslation('title', $locale, null);
            $quote->setTranslation('notes', $locale, null);
        }
        $quote->status = QuoteStatus::Presented->value;
        $quote->save();

        $changes = $this->activities(QuoteActivity::EVENT_QUOTE_UPDATED)->sole()->changes;
        $this->assertSame(['status'], array_keys($changes));
    }

    /** @test */
    public function empty_key_value_locales_added_by_nova_are_not_recorded_as_changes(): void
    {
        $quote = $this->makeQuote(['status' => QuoteStatus::New->value]);
        $quote->setTranslation('additional_services', 'it', ['Grafica' => '640'])->save();
        QuoteActivity::query()->delete();

        // Come NovaTabTranslatable su un KeyValue: una lingua vuota arriva come "{}" e si salva come [].
        foreach (['en', 'fr', 'es', 'de'] as $locale) {
            $quote->setTranslation('additional_services', $locale, json_decode('{}', true));
        }
        $quote->status = QuoteStatus::Presented->value;
        $quote->save();

        $changes = $this->activities(QuoteActivity::EVENT_QUOTE_UPDATED)->sole()->changes;
        $this->assertSame(['status'], array_keys($changes));
    }

    /** @test */
    public function real_change_on_translated_field_is_still_recorded(): void
    {
        $quote = $this->makeQuote(['title' => 'Preventivo A']);
        $quote->setTranslation('title', 'de', null)->save();
        QuoteActivity::query()->delete();

        $quote->setTranslation('title', 'it', 'Preventivo B')->save();

        $changes = $this->activities(QuoteActivity::EVENT_QUOTE_UPDATED)->sole()->changes;
        $this->assertSame(['title'], array_keys($changes));
        // Si registrano solo le lingue con un valore, senza il "de": null aggiunto da Nova.
        $this->assertSame(['it' => 'Preventivo A'], $changes['title']['old']);
        $this->assertSame(['it' => 'Preventivo B'], $changes['title']['new']);
    }

    /** @test */
    public function kanban_move_records_status_without_priority(): void
    {
        // Come KanbanController::updateStatus(): stato e priority cambiano nello stesso save().
        $quote = $this->makeQuote(['status' => QuoteStatus::New->value]);

        $quote->status = QuoteStatus::Presented->value;
        $quote->priority = 15;
        $quote->save();

        $changes = $this->activities(QuoteActivity::EVENT_QUOTE_UPDATED)->sole()->changes;
        $this->assertSame(['status'], array_keys($changes));
    }

    /** @test */
    public function deleted_quote_keeps_readable_rows(): void
    {
        $quote = $this->makeQuote();

        $quote->delete();

        $deleted = $this->activities(QuoteActivity::EVENT_QUOTE_DELETED)->sole();
        $this->assertNull($deleted->quote_id);
        $this->assertSame('Preventivo Comune di X', $deleted->quote_title);
        $this->assertNull($this->activities(QuoteActivity::EVENT_QUOTE_CREATED)->sole()->quote_id);
    }

    /** @test */
    public function action_without_authenticated_user_is_recorded_with_empty_user(): void
    {
        auth()->logout();

        $this->makeQuote();

        $activity = $this->activities(QuoteActivity::EVENT_QUOTE_CREATED)->sole();
        $this->assertNull($activity->user_id);
        $this->assertNull($activity->user_name);
    }

    /** @test */
    public function products_attached_and_detached_from_api_are_recorded(): void
    {
        Sanctum::actingAs($this->user);
        $quote = $this->makeQuote();
        $product = Product::factory()->create(['name' => 'App Android']);
        $recurring = RecurringProduct::factory()->create();

        $this->postJson("/api/quotes/{$quote->id}/products/{$product->id}", ['quantity' => 1])->assertSuccessful();
        $this->deleteJson("/api/quotes/{$quote->id}/products/{$product->id}")->assertSuccessful();
        $this->postJson("/api/quotes/{$quote->id}/recurring-products/{$recurring->id}", ['quantity' => 1])->assertSuccessful();
        $this->deleteJson("/api/quotes/{$quote->id}/recurring-products/{$recurring->id}")->assertSuccessful();

        $added = $this->activities(QuoteActivity::EVENT_PRODUCT_ADDED)->sole();
        $this->assertSame($quote->id, $added->quote_id);
        $this->assertSame(['product_id' => $product->id, 'product_name' => 'App Android'], $added->changes);
        $this->assertCount(1, $this->activities(QuoteActivity::EVENT_PRODUCT_REMOVED));
        $this->assertCount(1, $this->activities(QuoteActivity::EVENT_RECURRING_PRODUCT_ADDED));
        $this->assertCount(1, $this->activities(QuoteActivity::EVENT_RECURRING_PRODUCT_REMOVED));
    }

    /** @test */
    public function products_attached_and_detached_on_the_relation_are_recorded(): void
    {
        // Nova BelongsToMany passa dalla relazione, come qui.
        $quote = $this->makeQuote();
        $product = Product::factory()->create();

        $quote->products()->attach($product->id, ['quantity' => 2]);
        $quote->products()->detach($product->id);

        $this->assertCount(1, $this->activities(QuoteActivity::EVENT_PRODUCT_ADDED));
        $this->assertCount(1, $this->activities(QuoteActivity::EVENT_PRODUCT_REMOVED));
        $this->assertSame(0, $quote->products()->count());
    }

    /** @test */
    public function products_copied_by_duplicate_quote_are_recorded(): void
    {
        $quote = $this->makeQuote();
        $quote->products()->attach(Product::factory()->create()->id, ['quantity' => 3]);
        QuoteActivity::query()->delete();

        (new DuplicateQuote)->handle(new ActionFields(collect(), collect()), collect([$quote->fresh()]));

        $added = $this->activities(QuoteActivity::EVENT_PRODUCT_ADDED)->sole();
        $this->assertNotSame($quote->id, $added->quote_id);
        $this->assertSame(3, Quote::find($added->quote_id)->products->first()->pivot->quantity);
    }

    /** @test */
    public function task_lifecycle_is_recorded(): void
    {
        $quote = $this->makeQuote();
        $task = Task::create(['quote_id' => $quote->id, 'title' => 'Richiamare il cliente', 'due_date' => now()->addDay()]);

        $task->update(['status' => Task::STATUS_COMPLETED]);
        $task->update(['status' => Task::STATUS_TODO]);
        $task->update(['title' => 'Richiamare il sindaco']);
        $task->delete();

        $created = $this->activities(QuoteActivity::EVENT_TASK_CREATED)->sole();
        $this->assertSame($quote->id, $created->quote_id);
        $this->assertSame('Richiamare il cliente', $created->task_title);
        $this->assertCount(1, $this->activities(QuoteActivity::EVENT_TASK_COMPLETED));
        $this->assertCount(1, $this->activities(QuoteActivity::EVENT_TASK_REOPENED));
        $updated = $this->activities(QuoteActivity::EVENT_TASK_UPDATED)->sole();
        $this->assertSame(['old' => 'Richiamare il cliente', 'new' => 'Richiamare il sindaco'], $updated->changes['title']);
        $this->assertArrayNotHasKey('completed_at', $updated->changes);
        $deleted = $this->activities(QuoteActivity::EVENT_TASK_DELETED)->sole();
        $this->assertNull($deleted->task_id);
        $this->assertSame('Richiamare il sindaco', $deleted->task_title);
    }

    /** @test */
    public function appended_note_records_only_the_added_text(): void
    {
        $quote = $this->makeQuote();
        $task = Task::create(['quote_id' => $quote->id, 'title' => 'Task', 'due_date' => now()->addDay(), 'notes' => 'Nota vecchia']);

        $task->appendNote('Il cliente richiama giovedì');

        $note = $this->activities(QuoteActivity::EVENT_TASK_NOTE_ADDED)->sole()->changes['note'];
        // Solo il testo della nota: niente nota vecchia, HTML o intestazione con autore e ora.
        $this->assertSame('Il cliente richiama giovedì', $note);
        $this->assertCount(0, $this->activities(QuoteActivity::EVENT_TASK_UPDATED));
    }

    /** @test */
    public function rewritten_note_is_recorded_as_update(): void
    {
        $quote = $this->makeQuote();
        $task = Task::create(['quote_id' => $quote->id, 'title' => 'Task', 'due_date' => now()->addDay(), 'notes' => 'Nota vecchia']);

        $task->update(['notes' => 'Nota corretta']);

        $this->assertCount(0, $this->activities(QuoteActivity::EVENT_TASK_NOTE_ADDED));
        $changes = $this->activities(QuoteActivity::EVENT_TASK_UPDATED)->sole()->changes;
        $this->assertSame(['old' => 'Nota vecchia', 'new' => 'Nota corretta'], $changes['notes']);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

use Tests\TestCase;

class StoryRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_story_children_creation_and_parent_assignment()
    {
        // Creazione delle storie padre e figlio
        $parentStory = Story::create(['name' => 'Parent Story']);
        $childStory1 = Story::create(['name' => 'Child Story 1']);
        $childStory2 = Story::create(['name' => 'Child Story 2']);

        // Assegnazione dei figli al genitore nella tabella pivot
        $parentStory->childStories()->attach($childStory1->id);
        $parentStory->childStories()->attach($childStory2->id);

        // Recupera il genitore tramite i figli e verifica che sia corretto
        $this->assertDatabaseHas('story_story', [
            'parent_id' => $parentStory->id,
            'child_id' => $childStory1->id
        ]);


        // Verifica che i figli abbiano esattamente un genitore e sia quello corretto
        $children = Story::find([$childStory1->id, $childStory2->id]);
        foreach ($children as $child) {
            $this->assertEquals($parentStory->id, $child->parent_id);
        }
    }

    /** @test */
    public function it_removes_child_associations_when_parent_is_deleted()
    {
        $parentStory = Story::create(['name' => 'Parent Story']);
        $childStory = Story::create(['name' => 'Child Story']);

        $parentStory->childStories()->attach($childStory->id);

        // Elimina la storia genitore
        $parentStory->delete();

        // Verifica che la relazione nella tabella pivot sia stata rimossa
        $this->assertDatabaseMissing('story_story', [
            'parent_id' => $parentStory->id,
            'child_id' => $childStory->id
        ]);
    }

    /** @test */
    public function it_correctly_references_the_parent_when_adding_a_child()
    {
        $parentStory = Story::create(['name' => 'Parent Story']);
        $childStory = Story::create(['name' => 'Child Story']);

        $parentStory->childStories()->attach($childStory->id);
        $parentStory = $parentStory->fresh();
        $childStory = $childStory->fresh();
        $parentOfChild = Story::find($childStory->parent_id);

        $this->assertNotNull($parentOfChild);
        $this->assertEquals($parentStory->id, $parentOfChild->id);
    }
    /** @test */
    public function it_propagates_status_changes_from_parent_to_child()
    {
        $parentStory = Story::create(['name' => 'Parent Story', 'status' => 'new']);
        $childStory = Story::create(['name' => 'Child Story', 'status' => 'new']);
        $user = User::factory()->create(); // Assicurati di avere una factory per gli utenti.
        $this->actingAs($user);

        $parentStory->childStories()->attach($childStory->id);
        $parentStory = $parentStory->fresh();
        $childStory = $childStory->fresh();
        $parentStory->update(['status' => 'done']);
        $parentStory = $parentStory->fresh();

        // Assumendo che lo stato debba propagarsi
        $updatedChild = $childStory->fresh();

        $this->assertEquals('done', $updatedChild->status);
    }
}

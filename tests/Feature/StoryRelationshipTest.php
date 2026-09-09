<?php

namespace Tests\Feature;

use App\Models\Story;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoryRelationshipTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function child_stories_e_una_relazione_hasmany_sulla_colonna_parent_id()
    {
        $parent = Story::create(['name' => 'Parent Story']);

        $this->assertInstanceOf(HasMany::class, $parent->childStories());
        $this->assertEquals('parent_id', $parent->childStories()->getForeignKeyName());
    }

    /** @test */
    public function il_padre_elenca_i_figli_collegati_solo_via_colonna_parent_id()
    {
        // Riproduce oc:8445: i figli sono collegati dalla sola colonna,
        // senza alcuna riga nel pivot story_story.
        $parent = Story::create(['name' => 'Parent Story']);
        $child1 = Story::create(['name' => 'Child 1', 'parent_id' => $parent->id]);
        $child2 = Story::create(['name' => 'Child 2', 'parent_id' => $parent->id]);

        $this->assertDatabaseCount('story_story', 0);

        $ids = $parent->fresh()->childStories->pluck('id')->sort()->values()->all();

        $this->assertEquals([$child1->id, $child2->id], $ids);
    }

    /** @test */
    public function il_figlio_referenzia_correttamente_il_padre()
    {
        $parent = Story::create(['name' => 'Parent Story']);
        $child = Story::create(['name' => 'Child Story', 'parent_id' => $parent->id]);

        $this->assertEquals($parent->id, $child->fresh()->parentStory->id);
    }

    /** @test */
    public function cancellando_il_padre_i_figli_restano_senza_padre()
    {
        $parent = Story::create(['name' => 'Parent Story']);
        $child = Story::create(['name' => 'Child Story', 'parent_id' => $parent->id]);

        $parent->delete();

        $this->assertNull($child->fresh()->parent_id);
    }

    /** @test */
    public function nessuna_riga_viene_piu_scritta_nel_pivot_story_story()
    {
        $parent = Story::create(['name' => 'Parent Story']);
        $child = Story::create(['name' => 'Child Story']);

        $child->parent_id = $parent->id;
        $child->save();

        $this->assertDatabaseCount('story_story', 0);
    }

    /** @test */
    public function lo_status_del_padre_non_si_propaga_piu_ai_figli()
    {
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $parent = Story::create(['name' => 'Parent Story', 'status' => 'new']);
        $child = Story::create(['name' => 'Child Story', 'status' => 'new', 'parent_id' => $parent->id]);

        // fresh() e' necessario: l'hook created esegue un save() interno che
        // desincronizza l'istanza in memoria e rende isDirty('status') falso.
        // Nova lavora sempre su istanze ricaricate dal DB.
        $parent = $parent->fresh();
        $parent->update(['status' => 'done']);

        $this->assertEquals('new', $child->fresh()->status);
    }

    /** @test */
    public function una_storia_con_figli_non_puo_diventare_figlia_e_lancia_un_errore_di_validazione()
    {
        $grandparent = Story::create(['name' => 'Grandparent']);
        $parent = Story::create(['name' => 'Parent']);
        Story::create(['name' => 'Child', 'parent_id' => $parent->id]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $parent->parent_id = $grandparent->id;
        $parent->save();
    }
}

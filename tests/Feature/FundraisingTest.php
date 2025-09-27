<?php

namespace Tests\Feature;

use App\Enums\StoryType;
use App\Enums\UserRole;
use App\Models\FundraisingOpportunity;
use App\Models\FundraisingProject;
use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FundraisingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Creo utenti di test
        $this->fundraisingUser = User::factory()->create();
        $this->fundraisingUser->roles = [UserRole::Fundraising];
        $this->fundraisingUser->save();

        $this->customerUser = User::factory()->create();
        $this->customerUser->roles = [UserRole::Customer];
        $this->customerUser->save();
    }

    /** @test */
    public function fundraising_user_can_create_opportunity()
    {
        $this->actingAs($this->fundraisingUser);

        $opportunityData = [
            'name' => 'Bando Test 2024',
            'official_url' => 'https://example.com/bando',
            'endowment_fund' => 1000000.00,
            'deadline' => now()->addDays(30),
            'program_name' => 'Programma Test',
            'sponsor' => 'Ente Test',
            'cofinancing_quota' => 80.00,
            'max_contribution' => 500000.00,
            'territorial_scope' => 'national',
            'beneficiary_requirements' => 'Requisiti per beneficiario',
            'lead_requirements' => 'Requisiti per capofila',
            'responsible_user_id' => $this->fundraisingUser->id,
        ];

        $opportunity = FundraisingOpportunity::create(array_merge($opportunityData, [
            'created_by' => $this->fundraisingUser->id,
        ]));

        $this->assertDatabaseHas('fundraising_opportunities', [
            'name' => 'Bando Test 2024',
            'created_by' => $this->fundraisingUser->id,
        ]);

        $this->assertEquals($this->fundraisingUser->id, $opportunity->responsible_user_id);
        $this->assertEquals('national', $opportunity->territorial_scope);
        $this->assertFalse($opportunity->isExpired());
    }

    /** @test */
    public function fundraising_user_can_create_project()
    {
        $this->actingAs($this->fundraisingUser);

        // Prima creo un'opportunità
        $opportunity = FundraisingOpportunity::create([
            'name' => 'Bando Test 2024',
            'deadline' => now()->addDays(30),
            'territorial_scope' => 'national',
            'created_by' => $this->fundraisingUser->id,
            'responsible_user_id' => $this->fundraisingUser->id,
        ]);

        // Poi creo un progetto
        $projectData = [
            'title' => 'Progetto Test',
            'fundraising_opportunity_id' => $opportunity->id,
            'lead_user_id' => $this->customerUser->id,
            'created_by' => $this->fundraisingUser->id,
            'responsible_user_id' => $this->fundraisingUser->id,
            'description' => 'Descrizione del progetto test',
            'status' => 'draft',
            'requested_amount' => 100000.00,
        ];

        $project = FundraisingProject::create($projectData);

        $this->assertDatabaseHas('fundraising_projects', [
            'title' => 'Progetto Test',
            'lead_user_id' => $this->customerUser->id,
        ]);

        $this->assertEquals('draft', $project->status);
        $this->assertEquals(100000.00, $project->requested_amount);
        $this->assertTrue($project->isUserInvolved($this->customerUser->id));
    }

    /** @test */
    public function customer_can_express_interest_via_story()
    {
        $this->actingAs($this->customerUser);

        // Creo un'opportunità
        $opportunity = FundraisingOpportunity::create([
            'name' => 'Bando Test 2024',
            'deadline' => now()->addDays(30),
            'territorial_scope' => 'national',
            'created_by' => $this->fundraisingUser->id,
            'responsible_user_id' => $this->fundraisingUser->id,
        ]);

        // Creo una story per esprimere interesse
        $story = Story::create([
            'name' => 'Interesse per: ' . $opportunity->name,
            'description' => 'Sono interessato a questa opportunità di finanziamento',
            'creator_id' => $this->customerUser->id,
            'type' => StoryType::Ticket->value,
            'status' => 'new',
        ]);

        $this->assertDatabaseHas('stories', [
            'name' => 'Interesse per: ' . $opportunity->name,
            'creator_id' => $this->customerUser->id,
            'type' => StoryType::Ticket->value,
        ]);
    }

    /** @test */
    public function customer_can_see_only_involved_projects()
    {
        $this->actingAs($this->customerUser);

        // Creo un'opportunità
        $opportunity = FundraisingOpportunity::create([
            'name' => 'Bando Test 2024',
            'deadline' => now()->addDays(30),
            'territorial_scope' => 'national',
            'created_by' => $this->fundraisingUser->id,
            'responsible_user_id' => $this->fundraisingUser->id,
        ]);

        // Creo un progetto dove il customer è capofila
        $project1 = FundraisingProject::create([
            'title' => 'Progetto dove sono capofila',
            'fundraising_opportunity_id' => $opportunity->id,
            'lead_user_id' => $this->customerUser->id,
            'created_by' => $this->fundraisingUser->id,
            'responsible_user_id' => $this->fundraisingUser->id,
            'status' => 'draft',
        ]);

        // Creo un altro customer
        $anotherCustomer = User::factory()->create();
        $anotherCustomer->roles = [UserRole::Customer];
        $anotherCustomer->save();

        // Creo un progetto dove il customer è partner
        $project2 = FundraisingProject::create([
            'title' => 'Progetto dove sono partner',
            'fundraising_opportunity_id' => $opportunity->id,
            'lead_user_id' => $anotherCustomer->id,
            'created_by' => $this->fundraisingUser->id,
            'responsible_user_id' => $this->fundraisingUser->id,
            'status' => 'draft',
        ]);

        // Aggiungo il customer come partner
        $project2->partners()->attach($this->customerUser->id);

        // Creo un progetto dove il customer NON è coinvolto
        $project3 = FundraisingProject::create([
            'title' => 'Progetto dove NON sono coinvolto',
            'fundraising_opportunity_id' => $opportunity->id,
            'lead_user_id' => $anotherCustomer->id,
            'created_by' => $this->fundraisingUser->id,
            'responsible_user_id' => $this->fundraisingUser->id,
            'status' => 'draft',
        ]);

        // Verifico che il customer possa vedere solo i progetti coinvolti
        $this->assertTrue($project1->isUserInvolved($this->customerUser->id));
        $this->assertTrue($project2->isUserInvolved($this->customerUser->id));
        $this->assertFalse($project3->isUserInvolved($this->customerUser->id));
    }

    /** @test */
    public function opportunity_expiration_check_works()
    {
        $this->actingAs($this->fundraisingUser);

        // Creo un'opportunità scaduta
        $expiredOpportunity = FundraisingOpportunity::create([
            'name' => 'Bando Scaduto',
            'deadline' => now()->subDays(10),
            'territorial_scope' => 'national',
            'created_by' => $this->fundraisingUser->id,
            'responsible_user_id' => $this->fundraisingUser->id,
        ]);

        // Creo un'opportunità attiva
        $activeOpportunity = FundraisingOpportunity::create([
            'name' => 'Bando Attivo',
            'deadline' => now()->addDays(30),
            'territorial_scope' => 'national',
            'created_by' => $this->fundraisingUser->id,
            'responsible_user_id' => $this->fundraisingUser->id,
        ]);

        $this->assertTrue($expiredOpportunity->isExpired());
        $this->assertFalse($activeOpportunity->isExpired());
    }
}

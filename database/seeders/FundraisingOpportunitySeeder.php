<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\FundraisingOpportunity;
use App\Models\FundraisingProject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class FundraisingOpportunitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Ottieni utenti con ruolo fundraising per responsible_user_id e created_by
        $fundraisingUsers = User::whereJsonContains('roles', UserRole::Fundraising->value)->get();
        
        // Ottieni utenti con ruolo customer per lead_user_id
        $customerUsers = User::whereJsonContains('roles', UserRole::Customer->value)->get();

        // Se non ci sono utenti con i ruoli necessari, creali
        if ($fundraisingUsers->isEmpty()) {
            $fundraisingUsers = collect([
                User::factory()->create([
                    'name' => 'Fundraising User',
                    'email' => 'fundraising@example.com',
                    'roles' => [UserRole::Fundraising]
                ])
            ]);
        }

        if ($customerUsers->isEmpty()) {
            $customerUsers = collect([
                User::factory()->create([
                    'name' => 'Customer User',
                    'email' => 'customer@example.com',
                    'roles' => [UserRole::Customer]
                ])
            ]);
        }

        $territorialScopes = [
            'cooperation',
            'european',
            'national',
            'regional',
            'territorial',
            'municipalities',
        ];

        $sponsors = [
            'Fondazione Cariplo',
            'Fondazione CRT',
            'Fondazione Compagnia di San Paolo',
            'Regione Lombardia',
            'Regione Toscana',
            'Regione Emilia-Romagna',
            'Regione Veneto',
            'Commissione Europea',
            'Ministero dello Sviluppo Economico',
            'ANCI',
            'Unione Buddhista Italiana',
        ];

        $statuses = ['draft', 'submitted', 'approved', 'rejected', 'completed'];
        
        $programNames = [
            'Programma di Sviluppo Rurale',
            'Fondo Europeo di Sviluppo Regionale',
            'Programma Operativo Nazionale',
            'Fondo Sociale Europeo',
            'Programma Interreg',
            'Horizon Europe',
            'Life Programme',
            'Erasmus+',
            'Creative Europe',
            'Programma di Cooperazione Territoriale',
        ];

        // Crea 100 opportunità
        for ($i = 1; $i <= 100; $i++) {
            $deadline = Carbon::now()->addDays(rand(30, 365));
            $endowmentFund = fake()->randomFloat(2, 10000, 5000000);
            $cofinancingQuota = fake()->randomFloat(2, 0, 100);
            $maxContribution = fake()->randomFloat(2, 5000, 1000000);
            
            $opportunity = FundraisingOpportunity::create([
                'name' => 'Bando Seeder #' . $i . ' - ' . fake()->words(3, true),
                'official_url' => 'https://' . fake()->domainName() . '/bando/' . fake()->slug(),
                'endowment_fund' => $endowmentFund,
                'deadline' => $deadline,
                'program_name' => fake()->randomElement($programNames),
                'sponsor' => fake()->randomElement($sponsors),
                'cofinancing_quota' => $cofinancingQuota,
                'max_contribution' => $maxContribution,
                'territorial_scope' => fake()->randomElement($territorialScopes),
                'beneficiary_requirements' => fake()->paragraphs(rand(2, 5), true),
                'lead_requirements' => fake()->paragraphs(rand(2, 4), true),
                'created_by' => $fundraisingUsers->random()->id,
                'responsible_user_id' => $fundraisingUsers->random()->id,
            ]);

            // Determina il numero di progetti da creare
            // Circa il 50% delle opportunità avrà 0 progetti
            $numProjects = fake()->boolean(50) ? 0 : fake()->numberBetween(1, 10);

            // Crea i progetti associati
            for ($j = 1; $j <= $numProjects; $j++) {
                $requestedAmount = fake()->randomFloat(2, 1000, min(100000, $maxContribution));
                $submissionDate = fake()->dateTimeBetween('-1 year', 'now');
                $decisionDate = null;
                $approvedAmount = null;
                
                // Se lo status è submitted, approved, rejected o completed, aggiungi decision_date
                $status = fake()->randomElement($statuses);
                if (in_array($status, ['submitted', 'approved', 'rejected', 'completed'])) {
                    $decisionDate = fake()->dateTimeBetween($submissionDate, 'now');
                    
                    // Se approvato o completato, aggiungi approved_amount
                    if (in_array($status, ['approved', 'completed'])) {
                        $approvedAmount = fake()->randomFloat(2, $requestedAmount * 0.5, $requestedAmount);
                    }
                }
                
                FundraisingProject::create([
                    'title' => 'Progetto ' . $j . ' - ' . fake()->words(4, true),
                    'fundraising_opportunity_id' => $opportunity->id,
                    'lead_user_id' => $customerUsers->random()->id,
                    'created_by' => $fundraisingUsers->random()->id,
                    'responsible_user_id' => $fundraisingUsers->random()->id,
                    'description' => fake()->paragraphs(rand(3, 6), true),
                    'status' => $status,
                    'requested_amount' => $requestedAmount,
                    'approved_amount' => $approvedAmount,
                    'submission_date' => $submissionDate,
                    'decision_date' => $decisionDate,
                ]);
            }
        }

        $this->command->info('Created 100 fundraising opportunities with random projects (0-10 per opportunity, ~50% with 0 projects)');
    }
}

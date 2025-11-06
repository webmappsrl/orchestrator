<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🏢 Creating organizations...');

        // 1. Create "OTCO / SO" organization
        $this->createOtcoSoOrganization();

        // 2. Create "GR" organization
        $this->createGrOrganization();

        // 3. Create "GR [Regione]" organizations
        $this->createGrRegioneOrganizations();

        $this->command->info('✅ Organizations created successfully!');
    }

    /**
     * Create "OTCO / SO" organization and associate users starting with "OTCO/SO"
     */
    private function createOtcoSoOrganization(): void
    {
        $this->command->line('   Creating "OTCO / SO" organization...');
        
        $organization = Organization::firstOrCreate([
            'name' => 'OTCO / SO'
        ]);

        $users = User::where('name', 'like', 'OTCO/SO%')->get();
        
        if ($users->isEmpty()) {
            $this->command->warn('   ⚠️  No users found starting with "OTCO/SO"');
            return;
        }

        $organization->users()->syncWithoutDetaching($users->pluck('id')->toArray());
        $this->command->line("   ✅ Associated {$users->count()} users to 'OTCO / SO'");
    }

    /**
     * Create "GR" organization and associate users starting with "GR "
     */
    private function createGrOrganization(): void
    {
        $this->command->line('   Creating "GR" organization...');
        
        $organization = Organization::firstOrCreate([
            'name' => 'GR'
        ]);

        $users = User::where('name', 'like', 'GR %')->get();
        
        if ($users->isEmpty()) {
            $this->command->warn('   ⚠️  No users found starting with "GR "');
            return;
        }

        $organization->users()->syncWithoutDetaching($users->pluck('id')->toArray());
        $this->command->line("   ✅ Associated {$users->count()} users to 'GR'");
    }

    /**
     * Create "GR [Regione]" organizations for each region
     */
    private function createGrRegioneOrganizations(): void
    {
        $this->command->line('   Creating "GR [Regione]" organizations...');

        // Get all users with names ending with "| [Regione]"
        $users = User::where('name', 'like', '%|%')->get();

        if ($users->isEmpty()) {
            $this->command->warn('   ⚠️  No users found with pattern "| [Regione]"');
            return;
        }

        // Extract unique regions from user names
        $regions = $users->map(function ($user) {
            // Extract region from pattern "| [Regione]"
            if (preg_match('/\| (.+)$/', $user->name, $matches)) {
                return trim($matches[1]);
            }
            return null;
        })->filter()->unique()->sort()->values();

        if ($regions->isEmpty()) {
            $this->command->warn('   ⚠️  No regions found in user names');
            return;
        }

        $totalUsers = 0;

        foreach ($regions as $regione) {
            $organizationName = "GR {$regione}";
            
            $organization = Organization::firstOrCreate([
                'name' => $organizationName
            ]);

            // Find users ending with "| {Regione}"
            $regionUsers = $users->filter(function ($user) use ($regione) {
                return preg_match('/\| ' . preg_quote($regione, '/') . '$/', $user->name);
            });

            if ($regionUsers->isNotEmpty()) {
                $organization->users()->syncWithoutDetaching($regionUsers->pluck('id')->toArray());
                $this->command->line("   ✅ Created '{$organizationName}' and associated {$regionUsers->count()} users");
                $totalUsers += $regionUsers->count();
            }
        }

        $this->command->line("   ✅ Created {$regions->count()} regional organizations with {$totalUsers} total user associations");
    }
}


<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Tag;
use App\Enums\UserRole;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

class InitializeDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:initialize-database {--force : Force initialization without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Initialize database with production-ready data (admin, developers, customers, tags)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Starting database initialization...');

        // Confirmation check
        if (!$this->option('force')) {
            if (!$this->confirm('⚠️  This will DELETE ALL DATA from the database. Are you sure?')) {
                $this->error('❌ Operation cancelled.');
                return 1;
            }
        }

        try {
            // Step 1: Clear database
            $this->clearDatabase();

            // Step 2: Run migrations
            $this->runMigrations();

            // Step 3: Seed initial data
            $this->seedInitialData();

            $this->info('✅ Database initialization completed successfully!');
            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Error during initialization: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Clear all data from database
     */
    private function clearDatabase()
    {
        $this->info('🗑️  Clearing database...');
        
        // Get all table names (PostgreSQL syntax)
        $tables = DB::select("
            SELECT tablename 
            FROM pg_tables 
            WHERE schemaname = 'public' 
            AND tablename != 'migrations'
        ");
        
        foreach ($tables as $table) {
            $tableName = $table->tablename;
            DB::statement("TRUNCATE TABLE {$tableName} RESTART IDENTITY CASCADE");
            $this->line("   Cleared table: {$tableName}");
        }
        
        $this->info('✅ Database cleared successfully!');
    }

    /**
     * Run database migrations
     */
    private function runMigrations()
    {
        $this->info('📋 Running migrations...');
        Artisan::call('migrate', ['--force' => true]);
        $this->info('✅ Migrations completed!');
    }

    /**
     * Seed initial data
     */
    private function seedInitialData()
    {
        $this->info('🌱 Seeding initial data...');

        // Create admin user
        $this->createAdminUser();

        // Create developer users (placeholder - will be updated with your list)
        $this->createDeveloperUsers();

        // Create customer users (placeholder - will be updated with your list)
        $this->createCustomerUsers();

        // Create tags (placeholder - will be updated with your list)
        $this->createTags();

        $this->info('✅ Initial data seeded successfully!');
    }

    /**
     * Create admin user
     */
    private function createAdminUser()
    {
        $this->line('   Creating admin user...');
        
        User::create([
            'name' => 'Montagna Servizi',
            'email' => 'info@montagnaservizi.com',
            'password' => bcrypt('M0ntagn@S3rviz!'),
            'roles' => [UserRole::Admin]
        ]);

        $this->line('   ✅ Admin user created: info@montagnaservizi.com (password: M0ntagn@S3rviz!)');
    }

    /**
     * Create developer users
     */
    private function createDeveloperUsers()
    {
        $this->line('   Creating developer users...');
        
        $developers = config('initialization.developers', []);
        
        if (empty($developers)) {
            $this->line('   ⚠️  No developers configured in config/initialization.php');
            return;
        }

        foreach ($developers as $developer) {
            // Convert string roles to UserRole enum
            $roles = array_map(function($role) {
                return UserRole::from($role);
            }, $developer['roles'] ?? ['developer']);

            User::create([
                'name' => $developer['name'],
                'email' => $developer['email'],
                'password' => bcrypt('developer123'),
                'roles' => $roles
            ]);
        }

        $this->line('   ✅ Developer users created');
    }

    /**
     * Create customer users
     */
    private function createCustomerUsers()
    {
        $this->line('   Creating customer users...');
        
        $customers = config('initialization.customers', []);
        
        if (empty($customers)) {
            $this->line('   ⚠️  No customers configured in config/initialization.php');
            return;
        }

        foreach ($customers as $customer) {
            User::create([
                'name' => $customer['name'],
                'email' => $customer['email'],
                'password' => bcrypt('customer123'),
                'roles' => [UserRole::Customer]
            ]);
        }

        $this->line('   ✅ Customer users created');
    }

    /**
     * Create tags
     */
    private function createTags()
    {
        $this->line('   Creating tags...');
        
        $tags = config('initialization.tags', []);
        
        if (empty($tags)) {
            $this->line('   ⚠️  No tags configured in config/initialization.php');
            return;
        }

        foreach ($tags as $tag) {
            Tag::create($tag);
        }

        $this->line('   ✅ Tags created');
    }
}

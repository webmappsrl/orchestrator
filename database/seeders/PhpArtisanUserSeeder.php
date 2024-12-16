<?php

namespace Database\Seeders;

use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class PhpArtisanUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Php artisan',
            'email' => 'orchestrator_artisan@webmapp.it',
            'password' => bcrypt('a very strong password that probably no one will use'),
        ])->markEmailAsVerified();
    }
}

<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class PhpArtisanUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::firstOrCreate(['email' => 'orchestrator_artisan@webmapp.it'], [
            'name' => 'Php artisan',
            'email' => 'orchestrator_artisan@webmapp.it',
            'password' => bcrypt('a very strong password that probably no one will use'),
        ]);
    }
}

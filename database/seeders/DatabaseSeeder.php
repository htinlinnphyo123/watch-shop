<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        User::create([
            'name' => 'Test User',
            'email' => 'user@example.com',
            'password' => Hash::make('password'),
            'role' => 'user',
        ]);
        
        \App\Models\Category::create(['name' => 'Luxury', 'slug' => 'luxury']);
        \App\Models\Category::create(['name' => 'Sport', 'slug' => 'sport']);
        \App\Models\Brand::create(['name' => 'Rolex', 'slug' => 'rolex']);
        \App\Models\Brand::create(['name' => 'Omega', 'slug' => 'omega']);
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@forgedesk.local',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'role' => 'admin',
            'is_active' => true,
        ]);
    }
}
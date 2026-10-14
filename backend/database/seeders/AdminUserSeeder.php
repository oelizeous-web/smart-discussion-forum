<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run()
    {
        // 1. Create the Admin User securely
        $admin = User::firstOrCreate(
            ['email' => 'admin@smartforum.com'], // Unique identifier
            [
                'name' => 'Administrator',
                'password' => Hash::make('Administrator'), // Change this!
                'agreed_to_rules' => true,
                'status' => 'ACTIVE',
                'email_verified_at' => now()
            ]
        );

        // 2. Assign the role
        $admin->assignRole('admin');
    }
}

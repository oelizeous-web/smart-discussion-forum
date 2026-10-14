<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;

class RegistrationService
{
    /**
     * Handle the registration logic for both Web and API.
     */
    public function registerUser(array $data, string $role = 'student')
    {
        // Enforce Requirement #5: Rules Agreement
        if (!isset($data['agreed_to_rules']) || $data['agreed_to_rules'] == false) {
            throw ValidationException::withMessages([
                'agreed_to_rules' => 'You must agree to the platform rules to register.'
            ]);
        }

        // 🔒 SECURITY CHECK: Define exactly which roles are allowed to register publicly.
        $allowedRoles = ['student', 'lecturer'];

        // If a user tries to modify the request to send 'admin', downgrade them to 'student'.
        if (!in_array($role, $allowedRoles)) {
            $role = 'student';
        }

        // Wrap the creation in a transaction so if the role fails, the user isn't half-created
        return DB::transaction(function () use ($data, $role) {
            
            // 1. Create the user
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'agreed_to_rules' => true,
            ]);

            // Note: User::create() automatically saves to the database. 
            // We just need to check if it successfully returned the model.
            if (!$user) {
                throw new \Exception("Failed to save user to database.");
            }

            // 2. Safely find or create the role (prevents the Spatie 500 error)
            $safeRole = Role::findOrCreate($role, 'web');

            // 3. Assign the role
            $user->assignRole($safeRole);

            return $user;
        });
    }
}
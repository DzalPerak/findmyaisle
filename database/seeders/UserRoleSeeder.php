<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create test users if they don't exist
        $adminUser = User::firstOrCreate([
            'email' => 'admin@shopnavi.com'
        ], [
            'name' => 'Admin User',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        $editorUser = User::firstOrCreate([
            'email' => 'editor@shopnavi.com'
        ], [
            'name' => 'Editor User', 
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        $regularUser = User::firstOrCreate([
            'email' => 'user@shopnavi.com'
        ], [
            'name' => 'Regular User',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        // Get roles (they should already exist from the migration)
        $adminRole = Role::where('name', 'admin')->first();
        $editorRole = Role::where('name', 'editor')->first();
        $userRole = Role::where('name', 'user')->first();

        // Assign roles to users
        if ($adminRole) {
            $adminUser->roles()->syncWithoutDetaching([$adminRole->id]);
            $this->command->info('Assigned admin role to admin@shopnavi.com');
        }

        if ($editorRole) {
            $editorUser->roles()->syncWithoutDetaching([$editorRole->id]);
            $this->command->info('Assigned editor role to editor@shopnavi.com');
        }

        if ($userRole) {
            $regularUser->roles()->syncWithoutDetaching([$userRole->id]);
            $this->command->info('Assigned user role to user@shopnavi.com');
        }

        $this->command->info('Test users created with roles:');
        $this->command->info('- admin@shopnavi.com (password: password) - Admin role');
        $this->command->info('- editor@shopnavi.com (password: password) - Editor role'); 
        $this->command->info('- user@shopnavi.com (password: password) - User role');
    }
}

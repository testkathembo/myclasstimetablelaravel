<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    public function run()
    {
        $roles = [
            'Admin',
            'Lecturer',
            'Student',
        ];

        // Ensure predefined roles exist
        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        // Create additional roles dynamically
        Role::factory(3)->create();
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run()
    {
        // Create Roles
        $superadmin = Role::create(['name' => 'superadmin']);
        $examoffice = Role::create(['name' => 'examoffice']);
        $schooladmin = Role::create(['name' => 'schooladmin']);
        $lecturer = Role::create(['name' => 'lecturer']);
        $student = Role::create(['name' => 'student']);

        // Define Permissions
        $permissions = [
            'manage users',
            'manage faculties',
            'manage units',
            'manage classrooms',
            'manage semesters',
            'manage timetable',
            'view timetable',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Assign Permissions to Roles
        $superadmin->givePermissionTo(Permission::all());
        $examoffice->givePermissionTo(['manage timetable']);
        $schooladmin->givePermissionTo(['manage faculties', 'manage units', 'manage classrooms']);
        $lecturer->givePermissionTo(['view timetable']);
        $student->givePermissionTo(['view timetable']);
    }
}
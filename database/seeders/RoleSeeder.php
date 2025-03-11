<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Roles
        $superAdmin = Role::create(['name' => 'SuperAdmin']);
        $schoolAdmin = Role::create(['name' => 'SchoolAdmin']);
        $examOffice = Role::create(['name' => 'ExamOffice']);
        $lecturer = Role::create(['name' => 'Lecturer']);
        $student = Role::create(['name' => 'Student']);

        // Create Permissions
        $permissions = [
            'manage users',
            'manage faculties',
            'manage units',
            'manage classrooms',
            'manage semesters',
            'manage timetable',
            'view timetable'
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Assign Permissions to Roles
        $superAdmin->givePermissionTo($permissions);
        $schoolAdmin->givePermissionTo(['manage faculties', 'manage units']);
        $examOffice->givePermissionTo(['manage timetable']);
        $lecturer->givePermissionTo(['view timetable']);
        $student->givePermissionTo(['view timetable']);
    }
}

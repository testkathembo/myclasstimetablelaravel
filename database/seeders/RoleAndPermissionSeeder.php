<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear cached permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Define permissions
        $permissions = [
            // Dashboard
            'view-dashboard',

            // User Management
            'manage-users',

            // Role and Permission Management
            'manage-roles',
            'manage-permissions',

            // Faculty Management
            'manage-faculties',

            // Classroom Management
            'manage-classrooms',

            // Unit Management
            'manage-units',

            // Semester Management
            'manage-semesters',

            // Enrollment Management
            'manage-enrollments',

            // Time Slot Management
            'manage-time-slots',

            // Timetable Management
            'manage-timetable',
           

            // Settings Management
            'manage-settings',

            // Timetable Download
            'download-timetable',

            // Conflict Resolution
            'process-timetable',
            'solve-conflicts',

            // Lecturer and Student-Specific Permissions
            'view-own-timetable',
            'download-own-timetable',

            // Actions
            'view',
            'create',
            'edit',
            'delete',
        ];

        // Create permissions
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Define roles and assign permissions
        $roles = [
            'Admin' => $permissions, // Full access to all permissions

            'Exam office' => [
                'view-dashboard',
                'manage-timetable',
                'manage-time-slots',
                'manage-classrooms',
                'manage-units',
                'manage-semesters', // Ensure this permission is included
                'manage-enrollments',
                
                'solve-conflicts',
            ],

            'Faculty Admin' => [
                'view-dashboard',
                'manage-faculties',
                'manage-units',
                'manage-semesters', // Ensure this permission is included
                'manage-enrollments',
                'download-timetable',
            ],

            'Lecturer' => [
                'view-dashboard',
                'view-own-timetable',
                'download-own-timetable',
            ],

            'Student' => [
                'view-dashboard',
                'view-own-timetable',
                'download-own-timetable',
            ],
        ];

        // Create roles and assign permissions
        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            $role->syncPermissions($rolePermissions);
        }
    }
}

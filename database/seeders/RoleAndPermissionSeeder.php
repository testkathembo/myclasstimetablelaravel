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

        // Define all permissions
        $permissions = [
            // Dashboard
            'view-dashboard',

            // Administration
            'manage-users', 'view-users', 'create-users', 'edit-users', 'delete-users',
            'manage-roles', 'view-roles', 'create-roles', 'edit-roles', 'delete-roles',
            'manage-permissions', 'view-permissions',
            'manage-settings', 'view-settings',

            // Academic Management
            'manage-schools', 'view-schools', 'create-schools', 'edit-schools', 'delete-schools',
            'manage-programs', 'view-programs', 'create-programs', 'edit-programs', 'delete-programs',
            'manage-units', 'view-units', 'create-units', 'edit-units', 'delete-units',
            'manage-classes', 'view-classes', 'create-classes', 'edit-classes', 'delete-classes',
            'manage-enrollments', 'view-enrollments', 'create-enrollments', 'edit-enrollments', 'delete-enrollments',
            'manage-semesters', 'view-semesters', 'create-semesters', 'edit-semesters', 'delete-semesters',
            'manage-classrooms', 'view-classrooms', 'create-classrooms', 'edit-classrooms', 'delete-classrooms',

            // Timetables

            'manage-class-timetables', 'view-class-timetables', 'create-class-timetables', 'edit-class-timetables', 'delete-class-timetables',
            'process-class-timetables', 'solve-class-conflicts', 'download-class-timetables',
            'view-own-class-timetables', 'download-own-class-timetables',
            'manage-exam-timetables', 'view-exam-timetables', 'create-exam-timetables', 'edit-exam-timetables', 'delete-exam-timetables',
            'process-exam-timetables', 'solve-exam-conflicts', 'download-exam-timetables',
            'view-own-exam-timetables', 'download-own-exam-timetables',
            'manage-exam-rooms', 'view-exam-rooms', 'create-exam-rooms', 'edit-exam-rooms', 'delete-exam-rooms',

            // Time Slots
            'manage-time-slots', 'view-time-slots',

            
        ];

        // Create all permissions
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Get all permission names for Admin role
        $allPermissions = Permission::pluck('name')->toArray();

        // Define roles with their specific permissions
        $rolePermissions = [
            'Admin' => $allPermissions, // Admin gets ALL permissions

            'Exam Office' => [
                'view-dashboard',
                // Exam management
                'manage-exam-timetables', 'view-exam-timetables', 'create-exam-timetables', 
                'edit-exam-timetables', 'delete-exam-timetables',
                'process-exam-timetables', 'solve-exam-conflicts', 'download-exam-timetables',
                'manage-exam-rooms', 'view-exam-rooms', 'create-exam-rooms', 
                'edit-exam-rooms', 'delete-exam-rooms',
                'manage-time-slots', 'view-time-slots',
                // View academic data
                'view-units', 'view-classes', 'view-enrollments', 'view-semesters', 'view-classrooms',
             
            ],

            'Faculty Admin' => [
                'view-dashboard',
                // Academic management (school-specific)
                'view-schools', 'view-programs',
                'manage-units', 'view-units', 'create-units', 'edit-units', 'delete-units',
                'manage-classes', 'view-classes', 'create-classes', 'edit-classes', 'delete-classes',
                'manage-enrollments', 'view-enrollments', 'create-enrollments', 
                'edit-enrollments', 'delete-enrollments',
                'view-semesters',
                'manage-classrooms', 'view-classrooms', 'create-classrooms', 
                'edit-classrooms', 'delete-classrooms',
                // Class timetables
                'manage-class-timetables', 'view-class-timetables', 'create-class-timetables',
                'edit-class-timetables', 'delete-class-timetables',
                'process-class-timetables', 'solve-class-conflicts', 'download-class-timetables',
                // Limited exam access
                'view-exam-timetables', 'download-exam-timetables',
                // User management (school-specific)
                'view-users',
                
            ],

            'Lecturer' => [
                'view-dashboard',
                // Own timetables only
                'view-own-class-timetables', 'download-own-class-timetables',
                'view-own-exam-timetables', 'download-own-exam-timetables',
                // Limited view access
                'view-units', 'view-classes', 'view-classrooms',
                
            ],

            'Student' => [
                'view-dashboard',
                // Own timetables only
                'view-own-class-timetables', 'download-own-class-timetables',
                'view-own-exam-timetables', 'download-own-exam-timetables',
                // Limited view access
                'view-units', 'view-classes', 'view-enrollments',
              
            ],
        ];

        // Create roles and assign permissions
        foreach ($rolePermissions as $roleName => $permissions) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            $role->syncPermissions($permissions);
            
            $this->command->info("Created role: {$roleName} with " . count($permissions) . " permissions");
        }

        $this->command->info('Roles and permissions seeded successfully!');
    }
}

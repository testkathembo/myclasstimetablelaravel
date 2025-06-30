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

        // Define permissions organized by sidebar sections
        $permissions = [
            // Dashboard
            'view-dashboard',

            // Administration
            'manage-users',
            'view-users',
            'create-users',
            'edit-users',
            'delete-users',
            
            'manage-roles',
            'view-roles',
            'create-roles',
            'edit-roles',
            'delete-roles',
            
            'manage-permissions',
            'view-permissions',
            
            'manage-settings',
            'view-settings',

            // Academic Management
            'manage-schools',
            'view-schools',
            'create-schools',
            'edit-schools',
            'delete-schools',
            
            'manage-programs',
            'view-programs',
            'create-programs',
            'edit-programs',
            'delete-programs',
            
            'manage-units',
            'view-units',
            'create-units',
            'edit-units',
            'delete-units',
            
            'manage-classes',
            'view-classes',
            'create-classes',
            'edit-classes',
            'delete-classes',
            
            'manage-enrollments',
            'view-enrollments',
            'create-enrollments',
            'edit-enrollments',
            'delete-enrollments',
            
            'manage-semesters',
            'view-semesters',
            'create-semesters',
            'edit-semesters',
            'delete-semesters',
            
            'manage-classrooms',
            'view-classrooms',
            'create-classrooms',
            'edit-classrooms',
            'delete-classrooms',

            // Timetables
            'manage-timetables',
            'view-timetables',
            
            'manage-class-timetables',
            'view-class-timetables',
            'create-class-timetables',
            'edit-class-timetables',
            'delete-class-timetables',
            'process-class-timetables',
            'solve-class-conflicts',
            'download-class-timetables',
            'download-own-class-timetables',
            'view-own-class-timetables',
            
            'manage-exam-timetables',
            'view-exam-timetables',
            'create-exam-timetables',
            'edit-exam-timetables',
            'delete-exam-timetables',
            'process-exam-timetables',
            'solve-exam-conflicts',
            'download-exam-timetables',
            'download-own-exam-timetables',
            'view-own-exam-timetables',
            
            'manage-exam-rooms',
            'view-exam-rooms',
            'create-exam-rooms',
            'edit-exam-rooms',
            'delete-exam-rooms',

            // Additional permissions
            'manage-time-slots',
            'view-time-slots',
            'manage-notifications',
            'view-notifications',
            'view-own-notifications',
            'generate-reports',
        ];

        // Create permissions
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Define roles and assign permissions
        $roles = [
            'Admin' => [
                // Full access to everything
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
                'manage-timetables', 'view-timetables',
                'manage-class-timetables', 'view-class-timetables', 'create-class-timetables', 'edit-class-timetables', 'delete-class-timetables',
                'process-class-timetables', 'solve-class-conflicts', 'download-class-timetables', 'download-own-class-timetables', 'view-own-class-timetables',
                'manage-exam-timetables', 'view-exam-timetables', 'create-exam-timetables', 'edit-exam-timetables', 'delete-exam-timetables',
                'process-exam-timetables', 'solve-exam-conflicts', 'download-exam-timetables', 'download-own-exam-timetables', 'view-own-exam-timetables',
                'manage-exam-rooms', 'view-exam-rooms', 'create-exam-rooms', 'edit-exam-rooms', 'delete-exam-rooms',
                
                // Additional
                'manage-time-slots', 'view-time-slots',
                'manage-notifications', 'view-notifications', 'view-own-notifications',
                'generate-reports',
            ],

            'Exam Office' => [
                'view-dashboard',
                
                // Exam-specific permissions
                'manage-exam-timetables', 'view-exam-timetables', 'create-exam-timetables', 'edit-exam-timetables',
                'process-exam-timetables', 'solve-exam-conflicts', 'download-exam-timetables',
                'manage-exam-rooms', 'view-exam-rooms', 'create-exam-rooms', 'edit-exam-rooms', 'delete-exam-rooms',
                'manage-time-slots', 'view-time-slots',
                
                // View-only access to academic data
                'view-units', 'view-classes', 'view-enrollments', 'view-semesters',
                'view-notifications', 'view-own-notifications',
            ],

            'Faculty Admin' => [
                'view-dashboard',
                
                // Academic Management (limited to their faculty)
                'view-schools', 'view-programs',
                'manage-units', 'view-units', 'create-units', 'edit-units',
                'manage-classes', 'view-classes', 'create-classes', 'edit-classes',
                'manage-enrollments', 'view-enrollments', 'create-enrollments', 'edit-enrollments',
                'view-semesters',
                'manage-classrooms', 'view-classrooms', 'create-classrooms', 'edit-classrooms',
                
                // Class Timetables
                'manage-class-timetables', 'view-class-timetables', 'create-class-timetables', 'edit-class-timetables',
                'process-class-timetables', 'solve-class-conflicts', 'download-class-timetables',
                
                // Limited exam timetable access
                'view-exam-timetables', 'download-exam-timetables',
                
                'view-notifications', 'view-own-notifications',
            ],

            'Lecturer' => [
                'view-dashboard',
                
                // View own data only
                'view-own-class-timetables', 'download-own-class-timetables',
                'view-own-exam-timetables', 'download-own-exam-timetables',
                'view-own-notifications',
                
                // Limited view access
                'view-units', 'view-classes', 'view-classrooms',
            ],

            'Student' => [
                'view-dashboard',
                
                // View own data only
                'view-own-class-timetables', 'download-own-class-timetables',
                'view-own-exam-timetables', 'download-own-exam-timetables',
                'view-own-notifications',
                
                // Limited view access
                'view-units', 'view-classes', 'view-enrollments',
            ],
        ];

        // Create roles and assign permissions
        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            $role->syncPermissions($rolePermissions);
        }
    }
}
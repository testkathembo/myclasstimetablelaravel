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

            // Exam Room Management
            'manage-examrooms',

            // Unit Management
            'manage-units',

            // Semester Management
            'manage-semesters',

            // Enrollment Management
            'manage-enrollments',

            // Time Slot Management
            'manage-time-slots',

            // Class Timetable Management
            'manage-classtimetables',
            'process-classtimetables',
            'solve-class-conflicts',
            'download-classtimetables',
            'download-own-classtimetables',

            // Exam Timetable Management
            'manage-examtimetables',
            'process-examtimetables',
            'solve-exam-conflicts',
            
            
             // Actions
            'create-examtimetables',
            'create-classtimetables',
            'update-examtimetables',
            'update-classtimetables',
            'delete-examtimetables',
            'delete-classtimetables',
            'download-examtimetables',
            'download-classtimetables',
            'download-own-examtimetables',
            'view-faculty-examtimetables',
            'view-faculty-classtimetables',
            'download-own-classtimetables',
            'view-examtimetables',
            'view-classtimetables',
            'view-own-examtimetables',
            'view-own-classtimetables',
            'view-examrooms',
            'view-classrooms',
            'view-units',
            'view-semesters',
            'view-enrollments',
            'view-faculties',
            'view-users',
            'view-roles',
            'view-permissions',
            'view-time-slots',
            'view-notifications',
            'view-own-notifications',
            

            // Notification Management
            'manage-notifications',

            // Report Management
            'generate-reports',
        ];

        // Create permissions
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Define roles and assign permissions
        $roles = [
            'Admin' => [
                'update-examtimetables', // Ensure this permission is included for the Admin role
                'view-dashboard',
                'manage-users',
                'manage-roles',
                'manage-permissions',
                'manage-faculties',
                'manage-classrooms',
                'manage-examrooms',
                'manage-units',
                'manage-semesters',
                'manage-enrollments',
                'manage-time-slots',
                'manage-classtimetables',
                'process-classtimetables',
                'solve-class-conflicts',
                'download-classtimetables',
                'download-own-classtimetables',
                'manage-examtimetables',
                'process-examtimetables',
                'solve-exam-conflicts',
                'create-examtimetables',
                'create-classtimetables',
                'update-classtimetables',
                'delete-examtimetables',
                'delete-classtimetables',
                'download-examtimetables',
                'download-classtimetables',
                'download-own-examtimetables',
                'view-faculty-examtimetables',
                'view-faculty-classtimetables',
                'view-examtimetables',
                'view-classtimetables',
                'view-own-examtimetables',
                'view-own-classtimetables',
                'view-examrooms',
                'view-classrooms',
                'view-units',
                'view-semesters',
                'view-enrollments',
                'view-faculties',
                'view-users',
                'view-roles',
                'view-permissions',
                'view-time-slots',
                'view-notifications',
                'view-own-notifications',
                'manage-notifications',
                'generate-reports',
            ],

            'Exam office' => [
                'view-dashboard',
                'manage-examtimetables',
                'process-examtimetables',
                'solve-exam-conflicts',
                'manage-time-slots',
                'manage-examrooms', // Manages exam rooms, not classrooms
                'download-examtimetables',
            ],

            'Faculty Admin' => [
                'view-dashboard',
                'manage-faculties',
                'manage-units',
                'manage-semesters',
                'manage-enrollments',
                'manage-classrooms', // Manages classrooms
                'manage-classtimetables',
                'process-classtimetables',
                'solve-class-conflicts',
                'download-classtimetables',
                'view-faculty-examtimetables', // Can view faculty-specific exam timetables
                'download-examtimetables', // Can download faculty-specific exam timetables
            ],

            'Lecturer' => [
                'view-dashboard',
                'download-own-classtimetables',
                'download-own-examtimetables',
            ],

            'Student' => [
                'view-dashboard',
                'download-own-classtimetables',
                'download-own-examtimetables',
            ],
        ];

        // Create roles and assign permissions
        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            $role->syncPermissions($rolePermissions);
        }
    }
}

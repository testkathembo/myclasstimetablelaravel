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
        //create cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        //create permissions
        $permissions = [
            'view-dashboard', 
            'manage-users', 'view-users', 'edit-users', 'delete-users',
            'manage-roles', 'view-roles', 'edit-roles', 'delete-roles',
            'manage-permissions', 'view-permissions', 'edit-permissions', 'delete-permissions',
            'manage-faculties', 'view-faculties', 'edit-faculties', 'delete-faculties',
            'manage-classrooms', 'view-classrooms', 'edit-classrooms', 'delete-classrooms',
            'manage-units', 'view-units', 'edit-units', 'delete-units',
            'manage-semesters', 'view-semesters', 'edit-semesters', 'delete-semesters',
            'manage-enrollments', 'view-enrollments', 'edit-enrollments', 'delete-enrollments',
            'manage-time-slots', 'view-time-slots', 'edit-time-slots', 'delete-time-slots',

            'create-timetable', 'view-timetable', 'edit-timetable', 'delete-timetable',

            'manage-settings', 'view-settings', 'edit-settings', 'delete-settings',

            'download-timetable',
            'process-timetable',
            'solve-conflicts',
            'manage-lecturers', 'view-lecturers', 'edit-lecturers', 'delete-lecturers',
            'manage-students', 'view-students', 'edit-students', 'delete-students',
            'manage-faculty-users', 'view-faculty-users', 'edit-faculty-users', 'delete-faculty-users',
            'manage-faculty-enrollments', 'view-faculty-enrollments', 'edit-faculty-enrollments', 'delete-faculty-enrollments',
            'view-own-units',
            'view-own-timetable', 'edit-own-timetable', 'delete-own-timetable',
            'download-own-timetable', 'download-faculty-timetable',
            'manage-faculty-units', 'view-faculty-units', 'edit-faculty-units', 'delete-faculty-units',
            'manage-faculty-semesters', 'view-faculty-semesters', 'edit-faculty-semesters', 'delete-faculty-semesters',

            
        ];
        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }
        //create roles
        $roles = [
            'Admin' => $permissions, // has full access

            'Exam office' => [
                'view-dashboard',
                'view-users',
                'create-timetable',
                'view-timetable',
                'edit-timetable',
                'manage-time-slots', 'view-time-slots', 'edit-time-slots', 'delete-time-slots',
                'view-units',
                'view-semesters',
                'view-enrollments',
                'manage-classrooms', 'view-classrooms', 
                'view-faculties',
                'download-timetable',
                'process-timetable',
                'solve-conflicts',
            ], // has limited access

            'Faculty Admin' => [
                'view-dashboard',
                'manage-faculty-users', 'view-faculty-users',
                'view-timetable',  'download-faculty-timetable',
                'manage-units', 'view-units',
                'manage-semesters', 'view-semesters',
                'manage-faculty-enrollments', 'view-faculty-enrollments',
                'manage-lecturers', 'view-lecturers',
                'manage-students', 'view-students',
               
            ], // has limited access

            'Lecturer' => [
                'view-dashboard',
                'view-own-units',
                'view-own-timetable',  'download-own-timetable',
                
                
            ], // has limited access

            'Student' => [
                'view-dashboard',
                'view-own-units',
                'view-own-timetable',  'download-own-timetable',
                
            ], // has limited access
        
        ];
        foreach ($roles as $role => $permissions) {
            $role = Role::firstOrCreate(['name' => $role]);
            $role->syncPermissions($permissions);
        }
    }
}

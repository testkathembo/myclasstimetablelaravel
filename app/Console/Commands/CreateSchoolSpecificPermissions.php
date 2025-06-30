<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\School;

class CreateSchoolSpecificPermissions extends Command
{
    protected $signature = 'permissions:create-school-specific';
    protected $description = 'Create school-specific permissions using Spatie';

    public function handle()
    {
        $this->info('Creating school-specific permissions...');

        // Clear permission cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Get all schools
        $schools = School::all();

        foreach ($schools as $school) {
            $schoolCode = strtolower($school->code);
            
            // Create school-specific permissions
            $schoolPermissions = [
                "manage {$schoolCode} school",
                "view {$schoolCode} school",
                "manage {$schoolCode} programs",
                "manage {$schoolCode} units",
                "manage {$schoolCode} classes",
                "manage {$schoolCode} enrollments",
                "manage {$schoolCode} timetables",
                "view {$schoolCode} dashboard",
            ];

            foreach ($schoolPermissions as $permissionName) {
                Permission::firstOrCreate(['name' => $permissionName]);
                $this->info("Created permission: {$permissionName}");
            }

            // Create or get school-specific Faculty Admin role
            $schoolFacultyRole = Role::firstOrCreate([
                'name' => "Faculty Admin - {$school->code}"
            ]);

            // Assign school-specific permissions to the role
            $schoolFacultyRole->syncPermissions($schoolPermissions);
            $this->info("Created role: Faculty Admin - {$school->code}");
        }

        // Update general permissions
        $generalPermissions = [
            'manage schools',
            'view schools',
            'manage all programs',
            'manage all units',
            'manage all classes',
            'manage all enrollments',
            'manage all timetables',
            'view admin dashboard',
        ];

        foreach ($generalPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Update Admin role to have all permissions
        $adminRole = Role::where('name', 'Admin')->first();
        if ($adminRole) {
            $adminRole->syncPermissions(Permission::all());
            $this->info('Updated Admin role with all permissions');
        }

        $this->info('School-specific permissions created successfully!');
        return 0;
    }
}

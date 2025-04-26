use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run()
    {
        // Create permissions
        $permissions = [
            'view-faculties',
            'manage-faculties',
            'download-own-timetable',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Assign permissions to roles
        $admin = Role::firstOrCreate(['name' => 'Admin']);
        $student = Role::firstOrCreate(['name' => 'Student']);

        $admin->givePermissionTo(['view-faculties', 'manage-faculties']);
        $student->givePermissionTo(['download-own-timetable']);
    }
}

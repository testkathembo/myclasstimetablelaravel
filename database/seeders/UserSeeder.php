<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Create roles if they don't exist
        $roles = ['SuperAdmin', 'SchoolAdmin', 'ExamOffice', 'Lecturer'];
        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        // Seed users
        $users = [
            [
                'code' => '112722',
                'first_name' => 'Benjamin',
                'last_name' => 'Mwangi',
                'faculty' => 'SCES',
                'email' => 'ben@strathmore.edu',
                'phone' => '0716571995',
                'password' => Hash::make('password'), // Change 'password' to a secure password
                'role' => 'SuperAdmin',
            ],
            [
                'code' => '112729',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'faculty' => 'SCES',
                'email' => 'john.doe@strathmore.edu',
                'phone' => '0706571996',
                'password' => Hash::make('password'), // Change 'password' to a secure password
                'role' => 'SchoolAdmin',
            ],
            [
                'code' => '112723',
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'faculty' => 'SCES',
                'email' => 'jane.do@strathmore.edu',
                'phone' => '0706571997',
                'password' => Hash::make('password'), // Change 'password' to a secure password
                'role' => 'ExamOffice',
            ],
            [
                'code' => '112724',
                'first_name' => 'JaneHello',
                'last_name' => 'DoeHell',
                'faculty' => 'SL',
                'email' => 'jane@strathmore.edu',
                'phone' => '0706571998',
                'password' => Hash::make('password'), // Change 'password' to a secure password
                'role' => 'Lecturer',
            ],
        ];
        

        foreach ($users as $userData) {
            $user = User::create($userData);
            $user->assignRole($userData['role']);
        }
    }
}

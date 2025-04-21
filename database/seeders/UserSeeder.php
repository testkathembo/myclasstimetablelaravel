<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Faker\Factory as Faker;

class UserSeeder extends Seeder
{
    public function run()
    <?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Create admin user
        $admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@example.com',
            'faculty' => 'Administration',
            'phone' => '1234567890',
            'code' => 'ADM001',
            'password' => Hash::make('password'),
        ]);
        $admin->assignRole('Admin');

        // Create exam office user
        $examOffice = User::create([
            'first_name' => 'Exam',
            'last_name' => 'Office',
            'email' => 'exam@example.com',
            'faculty' => 'Examination',
            'phone' => '1234567891',
            'code' => 'EXM001',
            'password' => Hash::make('password'),
        ]);
        $examOffice->assignRole('Exam office');

        // Create faculty admin user
        $facultyAdmin = User::create([
            'first_name' => 'Faculty',
            'last_name' => 'Admin',
            'email' => 'faculty@example.com',
            'faculty' => 'Science',  // Specify actual faculty
            'phone' => '1234567892',
            'code' => 'FAC001',
            'password' => Hash::make('password'),
        ]);
        $facultyAdmin->assignRole('Faculty Admin');

        // Create lecturer user
        $lecturer = User::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'lecturer@example.com',
            'faculty' => 'Science',  // Specify actual faculty
            'phone' => '1234567893',
            'code' => 'LEC001',
            'password' => Hash::make('password'),
        ]);
        $lecturer->assignRole('Lecturer');

        // Create student user
        $student = User::create([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'student@example.com',
            'faculty' => 'Science',  // Specify actual faculty
            'phone' => '1234567894',
            'code' => 'STU001',
            'password' => Hash::make('password'),
        ]);
        $student->assignRole('Student');
    }
}
    {
        $faker = Faker::create();
        $faculties = ['SBS', 'BBIT', 'SL', 'SH', 'TM'];

        // --- Create Key Users ---
    }
}
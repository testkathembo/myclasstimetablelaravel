<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Faker\Factory as Faker;

class UserSeeder extends Seeder
{
    public function run()
    {
        $faker = Faker::create();
        $faculties = ['SBS', 'BBIT', 'SL', 'SH', 'TM'];

        // --- Create Key Users ---
        $admin = User::create([
            'first_name' => 'Katsodieu',
            'last_name' => 'Dido',
            'email' => 'admin@gmail.com',
            'phone' => '0700000000',
            'password' => Hash::make('password'),
        ]);
        $admin->assignRole('Admin');

        $examoffice = User::create([
            'first_name' => 'Exam',
            'last_name' => 'Office',
            'email' => 'examoffice@gmail.com',
            'phone' => '0700000001',
            'password' => Hash::make('password'),
        ]);
        $examoffice->assignRole('Exam Office');

        $facultyadmin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'Faculty',
            'email' => 'faculty@gmail.com',
            'phone' => '0700000002',
            'password' => Hash::make('password'),
        ]);
        $facultyadmin->assignRole('Faculty Admin');

        $lecturer = User::create([
            'first_name' => 'John',
            'last_name' => 'Lecturer',
            'email' => 'lecturer@gmail.com',
            'phone' => '0700000003',
            'password' => Hash::make('password'),
        ]);
        $lecturer->assignRole('Lecturer');

        $student = User::create([
            'first_name' => 'Jane',
            'last_name' => 'Student',
            'email' => 'student@gmail.com',
            'phone' => '0700000004',
            'password' => Hash::make('password'),
        ]);
        $student->assignRole('Student');

        // --- Create 300 Students per Faculty ---
        foreach ($faculties as $faculty) {
            for ($i = 0; $i < 300; $i++) {
                $student = User::create([
                    'code' => $faker->unique()->numerify('######'),
                    'first_name' => $faker->firstName,
                    'last_name' => $faker->lastName,
                    'faculty' => $faculty,
                    'email' => $faker->unique()->safeEmail,
                    'phone' => $faker->unique()->numerify('07########'),
                    'password' => Hash::make('password'),
                ]);
                $student->assignRole('Student');
            }
        }

        // --- Add 50 Lecturers (10 per faculty) ---
        foreach ($faculties as $faculty) {
            for ($i = 0; $i < 10; $i++) {
                $lecturer = User::create([
                    'code' => $faker->unique()->numerify('L######'),
                    'first_name' => $faker->firstName,
                    'last_name' => $faker->lastName,
                    'faculty' => $faculty,
                    'email' => $faker->unique()->safeEmail,
                    'phone' => $faker->unique()->numerify('07########'),
                    'password' => Hash::make('password'),
                ]);
                $lecturer->assignRole('Lecturer');
            }
        }

        // --- Add 1 Additional Exam Officer ---
        $exam = User::create([
            'code' => 'EXAM001',
            'first_name' => 'Exam',
            'last_name' => 'Officer',
            'faculty' => 'All',
            'email' => 'exam.officer@example.com',
            'phone' => '0700000001',
            'password' => Hash::make('password'),
        ]);
        $exam->assignRole('Exam Office');

        // --- Add 5 Faculty Admins (1 per faculty) ---
        foreach ($faculties as $faculty) {
            $facAdmin = User::create([
                'code' => $faker->unique()->numerify('FA######'),
                'first_name' => $faker->firstName,
                'last_name' => $faker->lastName,
                'faculty' => $faculty,
                'email' => $faker->unique()->safeEmail,
                'phone' => $faker->unique()->numerify('07########'),
                'password' => Hash::make('password'),
            ]);
            $facAdmin->assignRole('Faculty Admin');
        }
    }
}

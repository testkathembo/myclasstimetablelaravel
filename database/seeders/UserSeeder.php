<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Faker\Factory as Faker;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $faker = Faker::create();
        $faculties = ['SBS', 'BBIT', 'SL', 'SH', 'TM']; // List of faculties

        foreach ($faculties as $faculty) {
            for ($i = 0; $i < 300; $i++) {
                User::create([
                    'code' => $faker->unique()->numerify('######'), // Generate a unique 6-digit code
                    'first_name' => $faker->firstName,
                    'last_name' => $faker->lastName,
                    'faculty' => $faculty,
                    'email' => $faker->unique()->safeEmail,
                    'phone' => $faker->unique()->numerify('07########'), // Generate a Kenyan phone number
                    'password' => Hash::make('password'), // Default password
                ]);
            }
        }

        // Add 50 lecturers (10 per faculty)
        foreach ($faculties as $faculty) {
            for ($i = 0; $i < 10; $i++) {
                User::create([
                    'code' => $faker->unique()->numerify('L######'), // Generate a unique lecturer code
                    'first_name' => $faker->firstName,
                    'last_name' => $faker->lastName,
                    'faculty' => $faculty,
                    'email' => $faker->unique()->safeEmail,
                    'phone' => $faker->unique()->numerify('07########'), // Generate a Kenyan phone number
                    'password' => Hash::make('password'), // Default password
                    'user_role' => 'lecturer', // Role: Lecturer
                ]);
            }
        }

        // Add 1 exam officer
        User::create([
            'code' => 'EXAM001',
            'first_name' => 'Exam',
            'last_name' => 'Officer',
            'faculty' => 'All',
            'email' => 'exam.officer@example.com',
            'phone' => '0700000001',
            'password' => Hash::make('password'), // Default password
            'user_role' => 'examofficer', // Role: Exam Officer
        ]);

        // Add 5 faculty admins (1 per faculty)
        foreach ($faculties as $faculty) {
            User::create([
                'code' => $faker->unique()->numerify('FA######'), // Generate a unique faculty admin code
                'first_name' => $faker->firstName,
                'last_name' => $faker->lastName,
                'faculty' => $faculty,
                'email' => $faker->unique()->safeEmail,
                'phone' => $faker->unique()->numerify('07########'), // Generate a Kenyan phone number
                'password' => Hash::make('password'), // Default password
                'user_role' => 'facultyadmin', // Role: Faculty Admin
            ]);
        }
    }
}

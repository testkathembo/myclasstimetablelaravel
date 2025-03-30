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
        $faculties = ['SBS', 'SCES', 'SL', 'SH', 'TM']; // List of faculties

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
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
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

        for ($i = 2; $i < 1000; $i++) {
            User::create([
                'code' => $faker->unique()->numerify('100002'), // Example: U001, U002, etc.
                'first_name' => $faker->firstName,
                'last_name' => $faker->lastName,
                'faculty' => $faker->randomElement(['SCES','SBS','SH','SL','STM']), // Replace with appropriate method if needed
                'email' => $faker->unique()->safeEmail,
                'email_verified_at' => now(), // Add email_verified_at field
                'phone' => $faker->phoneNumber,
                'role' => $faker->randomElement(['student']), // Example roles
                'password' => bcrypt('password'), // Default password
                
            ]);
        }
    }
}

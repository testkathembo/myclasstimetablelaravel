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
        $userCount = 200; // Random number between 1100 and 1200

        for ($i = 1; $i <= $userCount; $i++) {
            User::create([
                'code' => $faker->unique()->numerify('S####'), // Example: S001, S002, etc.
                'first_name' => $faker->firstName,
                'last_name' => $faker->lastName,
                'faculty' => $faker->randomElement(['SCES']), // Replace with appropriate method if needed
                'email' => $faker->unique()->safeEmail,
                'email_verified_at' => now(), // Add email_verified_at field
                'phone' => $faker->phoneNumber,
                'role' => $faker->randomElement(['student']), // Example roles
                'password' => bcrypt('password'), // Default password
            ]);
        }
    }
}

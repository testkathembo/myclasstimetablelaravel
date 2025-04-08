<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Database\Seeders\UnitSeeder;
use Database\Seeders\FacultySeeder;
use Database\Seeders\ClassroomSeeder;
use Database\Seeders\SemesterSeeder;
use Database\Seeders\GroupSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call([
            AdminSeeder::class,
            UserSeeder::class,
            UnitSeeder::class,
            FacultySeeder::class,
            ClassroomSeeder::class, // Added ClassroomSeeder
            SemesterSeeder::class, // Added SemesterSeeder
            TimeSlotSeeder::class,
          
            // Add other seeders here if needed
        ]);
        
    }
}
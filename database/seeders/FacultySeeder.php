<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Faculty;

class FacultySeeder extends Seeder
{
    public function run()
    {
        Faculty::factory(5)->create(); // Create 5 faculties
    }
}


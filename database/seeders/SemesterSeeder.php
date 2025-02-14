<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Semester;

class SemesterSeeder extends Seeder
{
    public function run()
    {
        Semester::factory(4)->create(); // Generate 4 random semesters
    }
}

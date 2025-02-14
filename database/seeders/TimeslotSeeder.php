<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Timeslot;

class TimeslotSeeder extends Seeder
{
    public function run()
    {
        Timeslot::factory(4)->create(); // Generate 10 random timeslots
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TimeSlotSeeder extends Seeder
{
    public function run()
    {
        $startTime = strtotime('08:00:00'); // Start time: 8:00 AM
        $endTime = strtotime('17:30:00'); // End time: 5:00 PM
        $slotDuration = 2 * 60 * 60; // 2 hours in seconds
        $breakDuration = 30 * 60; // 30 minutes in seconds

        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        $date = '2025-03-03'; // Starting date (Monday)

        $timeSlots = [];

        foreach ($days as $day) {
            $currentStartTime = $startTime;

            while ($currentStartTime + $slotDuration <= $endTime) {
                $timeSlots[] = [
                    'day' => $day,
                    'date' => $date,
                    'start_time' => date('H:i:s', $currentStartTime),
                    'end_time' => date('H:i:s', $currentStartTime + $slotDuration),
                ];

                // Move to the next slot (add slot duration + break duration)
                $currentStartTime += $slotDuration + $breakDuration;
            }

            // Increment the date for the next day
            $date = date('Y-m-d', strtotime($date . ' +1 day'));
        }

        // Insert the generated time slots into the database
        DB::table('time_slots')->insert($timeSlots);
    }
}

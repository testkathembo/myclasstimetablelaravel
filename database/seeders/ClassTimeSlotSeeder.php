<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ClassTimeSlotSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('class_time_slots')->insert([
            [
                'day' => 'Monday',
                'start_time' => '09:00:00',
                'end_time' => '10:00:00',
                'status' => 'Online',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'day' => 'Tuesday',
                'start_time' => '09:00:00',
                'end_time' => '10:00:00',
                'status' => 'Online',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'day' => 'Wednesday',
                'start_time' => '09:00:00',
                'end_time' => '10:00:00',
                'status' => 'Online',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'day' => 'Thursday',
                'start_time' => '09:00:00',
                'end_time' => '10:00:00',
                'status' => 'Online',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'day' => 'Friday',
                'start_time' => '09:00:00',
                'end_time' => '10:00:00',
                'status' => 'Online',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'day' => 'Monday',
                'start_time' => '10:30:00',
                'end_time' => '11:30:00',
                'status' => 'Online',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'day' => 'Tuesday',
                'start_time' => '10:30:00',
                'end_time' => '11:30:00',
                'status' => 'Online',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'day' => 'Wednesday',
                'start_time' => '10:30:00',
                'end_time' => '11:30:00',
                'status' => 'Online',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'day' => 'Thursday',
                'start_time' => '10:30:00',
                'end_time' => '11:30:00',
                'status' => 'Online',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'day' => 'Friday',
                'start_time' => '10:30:00',
                'end_time' => '11:30:00',
                'status' => 'Online',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            [
                'day' => 'Monday',
                'start_time' => '12:00:00',
                'end_time' => '13:00:00',
                'status' => 'Online',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'day' => 'Tuesday',
                'start_time' => '12:00:00',
                'end_time' => '13:00:00',
                'status' => 'Online',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'day' => 'Wednesday',
                'start_time' => '12:00:00',
                'end_time' => '13:00:00',
                'status' => 'Online',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'day' => 'Thursday',
                'start_time' => '12:00:00',
                'end_time' => '13:00:00',
                'status' => 'Online',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'day' => 'Friday',
                'start_time' => '12:00:00',
                'end_time' => '13:00:00',
                'status' => 'Online',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            [
                'day' => 'Monday',
                'start_time' => '13:30:00',
                'end_time' => '14:30:00',
                'status' => 'Online',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'day' => 'Tuesday',
                'start_time' => '13:30:00',
                'end_time' => '14:30:00',
                'status' => 'Online',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'day' => 'Wednesday',
                'start_time' => '13:30:00',
                'end_time' => '14:30:00',
                'status' => 'Online',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'day' => 'Thursday',
                'start_time' => '13:30:00',
                'end_time' => '14:30:00',
                'status' => 'Online',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'day' => 'Friday',
                'start_time' => '13:30:00',
                'end_time' => '14:30:00',
                'status' => 'Online',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            [
                'day' => 'Monday',
                'start_time' => '15:00:00',
                'end_time' => '16:00:00',
                'status' => 'Online',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'day' => 'Tuesday',
                'start_time' => '15:00:00',
                'end_time' => '16:00:00',
                'status' => 'Online',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'day' => 'Wednesday',
                'start_time' => '15:00:00',
                'end_time' => '16:00:00',
                'status' => 'Online',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'day' => 'Thursday',
                'start_time' => '15:00:00',
                'end_time' => '16:00:00',
                'status' => 'Online',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'day' => 'Friday',
                'start_time' => '15:00:00',
                'end_time' => '16:00:00',
                'status' => 'Online',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}

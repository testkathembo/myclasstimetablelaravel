<?php

namespace Database\Factories;

use App\Models\Timeslot;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

class TimeslotFactory extends Factory
{
    protected $model = Timeslot::class;

    public function definition(): array
    {
        $startTime = fake()->time('H:i:s'); // Random start time
        $endTime = Carbon::parse($startTime)->addHours(2)->format('H:i:s'); // Ensure at least 2-hour duration

        return [
            'day' => fake()->randomElement([
                'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday',
            ]),
            'start_time' => $startTime,
            'end_time' => $endTime,
        ];
    }
}

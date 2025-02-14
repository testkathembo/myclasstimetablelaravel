<?php

namespace Database\Factories;

use App\Models\Semester;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

class SemesterFactory extends Factory
{
    protected $model = Semester::class;

    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-1 years', 'now'); // Random past start date
        $endDate = Carbon::parse($startDate)->addMonths(4); // 4 months duration

        return [
            'name' => fake()->randomElement([
                'Jan-April ' . now()->year,
                'May-Aug ' . now()->year,
                'Sept-Dec ' . now()->year,
            ]),
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
    }
}

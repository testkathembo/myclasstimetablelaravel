<?php

namespace Database\Factories;

<?php

namespace Database\Factories;

use App\Models\Classroom;
use App\Models\Faculty;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClassroomFactory extends Factory
{
    protected $model = Classroom::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('C###')), // e.g. C101, C205
            'name' => fake()->randomElement([
                'PH1 A',
                'STMB-05',
                'STC 010',
                ]),
            'capacity' => fake()->numberBetween(20, 200), // Classroom capacity
            'location' => fake()->randomElement([
                'Phase 1',
                'Phase 2',
                'Phase 3',
                
            ]),
            'faculty_id' => Faculty::inRandomOrder()->first()->id ?? Faculty::factory()->create()->id,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Classroom;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClassroomFactory extends Factory
{
    protected $model = Classroom::class;

    public function definition()
    {
        return [
            'name' => $this->faker->word,
            'capacity' => $this->faker->numberBetween(10, 50),
            'location' => $this->faker->address,
        ];
    }
}

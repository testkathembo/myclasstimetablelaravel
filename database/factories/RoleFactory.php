<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement([
                'Admin',
                'Lecturer',
                'Student',
                'Coordinator',
                'Assistant Lecturer',
                'Department Head',
            ]),
        ];
    }
}

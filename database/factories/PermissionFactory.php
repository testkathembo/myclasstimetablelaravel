<?php

namespace Database\Factories;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

class PermissionFactory extends Factory
{
    protected $model = Permission::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement([
                'view timetable',
                'manage students',
                'manage classrooms',
                'assign roles',
                'view reports',
                'edit schedules',
            ]),
        ];
    }
}

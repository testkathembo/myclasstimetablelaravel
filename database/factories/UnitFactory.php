<?php
namespace Database\Factories;

use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Unit>
 */
class UnitFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('U###')), // e.g. U101, U205
            'name' => fake()->sentence(3), // Random Unit Name
            'faculty' => fake()->randomElement(['BBIT', 'ICS', 'BCOM', 'TOURISM', 'LAW']),
        ];
    }
}

